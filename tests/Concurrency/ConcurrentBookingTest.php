<?php

use App\Models\Booking;
use App\Models\User;
use App\Services\BookingRefused;
use App\Services\BookingService;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * No double bookings (docs/PLAN.md, section 5.3): several processes, each with its own database connection,
 * try to book at the same moment. These tests commit to the database, so they empty it with
 * DatabaseTruncation instead of rolling back a transaction (see tests/Pest.php).
 */

beforeEach(function () {
    if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
        $this->markTestSkipped('Needs the pcntl and posix PHP extensions.');
    }

    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
});

/**
 * Runs $attempt($i) in $count forked processes that start together. Returns each one's outcome: "booked",
 * the refusal, or the error.
 *
 * @return list<string>
 */
function race(int $count, Closure $attempt): array
{
    DB::disconnect();

    $startAt = microtime(true) + 0.5;
    $files = [];
    $pids = [];

    for ($i = 0; $i < $count; $i++) {
        $files[$i] = tempnam(sys_get_temp_dir(), 'race');
        $pid = pcntl_fork();

        if ($pid === 0) {
            DB::purge();
            time_nanosleep(0, max(0, (int) (($startAt - microtime(true)) * 1e9)));

            try {
                $attempt($i);
                $outcome = 'booked';
            } catch (BookingRefused $refused) {
                $outcome = $refused->refusal->value ?? 'refused';
            } catch (Throwable $error) {
                $outcome = 'error: '.$error->getMessage();
            }

            file_put_contents($files[$i], $outcome);

            // Leave at once, without running the test runner's shutdown code in the copy.
            posix_kill(getmypid(), SIGKILL);
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $outcomes = array_map(fn (string $file) => (string) file_get_contents($file), $files);
    array_map('unlink', $files);

    return $outcomes;
}

it('lets only one of several members book the same slot', function () {
    $members = User::factory()->count(8)->create()->all();
    [$start, $end] = slot('2026-10-06 12:00');

    $outcomes = race(8, fn (int $i) => app(BookingService::class)->book($members[$i], rink('A-1'), $start, $end));

    sort($outcomes);

    expect($outcomes)->toBe(['booked', 'occupied', 'occupied', 'occupied', 'occupied', 'occupied', 'occupied', 'occupied'])
        ->and(Booking::query()->count())->toBe(1);
});

it('lets a member book only one rink per day, however fast they tap', function () {
    $member = member();
    $rinks = ['A-1', 'A-2', 'A-3', 'B-1', 'B-2', 'B-3'];

    $outcomes = race(count($rinks), function (int $i) use ($member, $rinks) {
        [$start, $end] = slot('2026-10-06 '.(12 + $i % 5).':00');

        app(BookingService::class)->book($member, rink($rinks[$i]), $start, $end);
    });

    sort($outcomes);

    expect($outcomes)->toBe(['booked', 'one-rink-per-day', 'one-rink-per-day', 'one-rink-per-day', 'one-rink-per-day', 'one-rink-per-day'])
        ->and(Booking::query()->where('uid', $member->uid)->count())->toBe(1);
});
