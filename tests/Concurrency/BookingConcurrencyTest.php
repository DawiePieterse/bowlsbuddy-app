<?php

use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Rink;
use App\Models\User;
use App\Services\BookingRefused;
use App\Services\BookingService;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Facades\DB;

/*
 * PLAN.md 5.3, "No double bookings": two booking attempts at the same moment must end with
 * exactly one booking. Each attempt runs in a forked process with its own database connection,
 * synchronised on a barrier so both hit the transaction together.
 */

/**
 * Runs each attempt (uid + sid booking tomorrow 14:00-15:00) in its own process and returns the
 * sorted outcomes, e.g. ['booked', 'refused'].
 *
 * @param  array<string, array{int, int}>  $attempts  name => [uid, sid]
 * @return list<string>
 */
function raceBookings(array $attempts): array
{
    $start = now()->addDay()->setTime(14, 0)->toImmutable();

    $workspace = sys_get_temp_dir().'/booking-race-'.uniqid();
    mkdir($workspace);

    // No open handle may cross the forks: each process connects on its own.
    DB::disconnect();

    $pids = [];

    foreach ($attempts as $name => [$uid, $sid]) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Could not fork.');
        }

        if ($pid === 0) {
            // Child: fresh connection, wait for the others at the barrier, book, report, die
            // silently (SIGKILL skips PHPUnit's shutdown handlers, which belong to the parent).
            DB::purge();

            touch($workspace.'/ready-'.$name);

            $others = array_keys($attempts);
            $waited = 0;

            do {
                $ready = array_filter($others, fn ($other) => file_exists($workspace.'/ready-'.$other));
                usleep(5_000);
                $waited += 5_000;
            } while (count($ready) < count($others) && $waited < 5_000_000);

            try {
                app(BookingService::class)->create(
                    User::query()->findOrFail($uid),
                    Rink::query()->findOrFail($sid),
                    $start,
                    $start->addHour(),
                );

                $result = 'booked';
            } catch (BookingRefused) {
                $result = 'refused';
            } catch (Throwable $e) {
                $result = 'error: '.$e->getMessage();
            }

            file_put_contents($workspace.'/result-'.$name, $result);

            posix_kill(posix_getpid(), SIGKILL);
        }

        $pids[$name] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $results = array_map(
        fn ($name) => @file_get_contents($workspace.'/result-'.$name) ?: 'missing',
        array_keys($attempts),
    );

    array_map('unlink', glob($workspace.'/*') ?: []);
    rmdir($workspace);

    sort($results);

    return $results;
}

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
});

it('gives a slot to exactly one of two concurrent bookings', function () {
    $userA = User::query()->create(['alias' => 'A', 'status' => 'enabled', 'email' => 'a@example.com', 'pw' => 'pw']);
    $userB = User::query()->create(['alias' => 'B', 'status' => 'enabled', 'email' => 'b@example.com', 'pw' => 'pw']);
    $rink = Rink::query()->where('name', 'A-1')->firstOrFail();

    $results = raceBookings([
        'a' => [$userA->uid, $rink->sid],
        'b' => [$userB->uid, $rink->sid],
    ]);

    expect($results)->toBe(['booked', 'refused'])
        ->and(Booking::query()->where('status', 'single')->count())->toBe(1)
        ->and(Reservation::query()->count())->toBe(1);
});

it('keeps one rink per member per day under concurrency', function () {
    $user = User::query()->create(['alias' => 'A', 'status' => 'enabled', 'email' => 'a@example.com', 'pw' => 'pw']);
    $rinks = Rink::query()->whereIn('name', ['A-1', 'B-1'])->orderBy('name')->pluck('sid')->all();

    $results = raceBookings([
        'a' => [$user->uid, $rinks[0]],
        'b' => [$user->uid, $rinks[1]],
    ]);

    expect($results)->toBe(['booked', 'refused'])
        ->and(Booking::query()->where('status', 'single')->count())->toBe(1);
});
