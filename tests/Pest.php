<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
 * Tests run against MySQL/MariaDB (see phpunit.xml), the same kind of database as production, so
 * constraints and locking behave as they will live.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Tests where several processes share the database need committed data, so they empty it instead.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
 * Helpers for the booking rule tests. They expect the ClubSeeder's LCE setup: greens A and B with rinks A-1
 * to B-6, 12:00 to 17:00 in 60-minute slots, 2 players per rink, 14 days ahead, cancel up to 24 hours before.
 */

function rink(string $name): Rink
{
    return Rink::query()->where('name', $name)->firstOrFail();
}

function member(string $status = 'enabled'): User
{
    return User::factory()->withStatus($status)->create();
}

/** An assist with the given privileges. */
function staff(string ...$privileges): User
{
    $user = member('assist');

    foreach ($privileges as $privilege) {
        $user->setMeta('allow.'.$privilege, 'true');
    }

    return $user->fresh();
}

/**
 * The slot starting at $start ("2026-10-06 12:00"), $hours long.
 *
 * @return array{Carbon, Carbon}
 */
function slot(string $start, int $hours = 1): array
{
    $start = Carbon::parse($start);

    return [$start, $start->copy()->addHours($hours)];
}

/** Puts a booking straight into the database, without checking any rule. */
function booked(User $user, string $rink, string $start, int $players = 1, string $status = 'single', string $visibility = 'public', int $hours = 1): Booking
{
    [$from, $until] = slot($start, $hours);

    $booking = Booking::query()->create([
        'uid' => $user->uid, 'sid' => rink($rink)->sid, 'status' => $status, 'visibility' => $visibility, 'quantity' => $players,
    ]);

    $booking->reservations()->create([
        'date' => $from->toDateString(), 'time_start' => $from->format('H:i:s'), 'time_end' => $until->format('H:i:s'),
    ]);

    return $booking;
}

/** An event (blocked time) on one rink ("A-1"), a green ("green:B") or all rinks (null). */
function blockedBy(?string $on, string $start, string $end, string $name = 'Club day', string $status = 'enabled'): Event
{
    $rinkName = $on !== null && ! str_starts_with($on, 'green:') ? $on : null;

    $event = Event::query()->create([
        'sid' => $rinkName !== null ? rink($rinkName)->sid : null,
        'status' => $status,
        'datetime_start' => $start,
        'datetime_end' => $end,
    ]);

    $event->setMeta('name', $name);

    if ($on !== null && str_starts_with($on, 'green:')) {
        $event->setMeta('green', substr($on, 6));
    }

    return $event;
}
