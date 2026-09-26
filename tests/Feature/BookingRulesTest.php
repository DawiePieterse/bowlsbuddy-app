<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use App\Services\BookingRules;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

/*
 * The booking rules (PLAN.md 5.3), tested against the reference values in docs/REFERENCE-RULES.md:
 * LCE seed, "now" pinned to Monday 2026-10-05 13:00.
 */

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
});

function rules(): BookingRules
{
    return app(BookingRules::class);
}

function rink(string $name = 'A-1'): Rink
{
    return Rink::query()->where('name', $name)->firstOrFail();
}

function member(string $email = 'member@example.com'): User
{
    $user = User::query()->firstOrCreate(
        ['email' => $email],
        ['alias' => 'Member', 'status' => 'enabled', 'pw' => 'a-good-password'],
    );

    $user->setMeta('firstname', 'Some');
    $user->setMeta('lastname', 'Member');

    return $user;
}

function staff(): User
{
    $user = User::query()->firstOrCreate(
        ['email' => 'assist@example.com'],
        ['alias' => 'Assist', 'status' => 'assist', 'pw' => 'a-good-password'],
    );

    $user->setMeta('allow.calendar.create-single-bookings', 'true');
    $user->setMeta('allow.calendar.cancel-single-bookings', 'true');

    return $user;
}

/** @return array{Carbon\CarbonImmutable, Carbon\CarbonImmutable} */
function slot(string $time, int $daysAhead = 1): array
{
    $start = Carbon::parse('2026-10-05 '.$time)->addDays($daysAhead)->toImmutable();

    return [$start, $start->addHour()];
}

function bookSlot(User $user, string $time, int $daysAhead = 1, string $rinkName = 'A-1', int $quantity = 1): Booking
{
    [$start, $end] = slot($time, $daysAhead);

    $booking = Booking::query()->create([
        'uid' => $user->uid,
        'sid' => rink($rinkName)->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => $quantity,
    ]);

    $booking->reservations()->create([
        'date' => $start->format('Y-m-d'),
        'time_start' => $start->format('H:i:s'),
        'time_end' => $end->format('H:i:s'),
    ]);

    return $booking;
}

it('accepts a free slot on an open day', function () {
    [$start, $end] = slot('14:00');

    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();
});

it('refuses times outside the rink day', function (string $from, string $to) {
    $day = Carbon::parse('2026-10-06')->toImmutable();

    $start = $day->setTimeFromTimeString($from);
    $end = $day->setTimeFromTimeString($to);

    expect(rules()->refusal(member(), rink(), $start, $end))->not->toBeNull();
})->with([
    'before opening' => ['11:00', '12:00'],
    'past closing' => ['17:00', '18:00'],
    'over closing' => ['16:30', '17:30'],
    'backwards' => ['15:00', '14:00'],
]);

it('refuses a past day and a slot past its first half, keeps a slot within it', function () {
    // Yesterday
    [$start, $end] = slot('14:00', -1);
    expect(rules()->refusal(member(), rink(), $start, $end))->toBe('This time is already over.');

    // Today 12:00, now 13:00: more than half the hour gone
    [$start, $end] = slot('12:00', 0);
    expect(rules()->refusal(member(), rink(), $start, $end))->toBe('This time is already over.');

    // Today 13:00 slot at 13:29: still within its first half hour
    Carbon::setTestNow('2026-10-05 13:29');
    [$start, $end] = slot('13:00', 0);
    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();

    // The same slot one minute past the half
    Carbon::setTestNow('2026-10-05 13:31');
    expect(rules()->refusal(member(), rink(), $start, $end))->not->toBeNull();
});

it('lets a user with calendar.see-past book past slots', function () {
    $admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    [$start, $end] = slot('12:00', 0);

    expect(rules()->refusal($admin, rink(), $start, $end))->toBeNull();
});

it('enforces the 14 day booking window on the clock, staff exempt', function () {
    // 12:00 slot 14 days ahead, now 13:00: within now + 14 days
    [$start, $end] = slot('12:00', 14);
    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();

    // 14:00 slot 14 days ahead: an hour past now + 14 days
    [$start, $end] = slot('14:00', 14);
    expect(rules()->refusal(member(), rink(), $start, $end))->toBe('This date is too far ahead to book.');

    // 15 days ahead
    [$start, $end] = slot('12:00', 15);
    expect(rules()->refusal(member(), rink(), $start, $end))->toBe('This date is too far ahead to book.')
        ->and(rules()->refusal(staff(), rink(), $start, $end))->toBeNull();
});

it('refuses a slot already booked, capacity not heterogenic', function () {
    bookSlot(member('other@example.com'), '14:00');

    [$start, $end] = slot('14:00');

    expect(rules()->refusal(member(), rink(), $start, $end))->toBe('This rink is already booked for this time.');
});

it('accepts the neighbouring slot and the same time on another rink', function () {
    bookSlot(member('other@example.com'), '14:00');

    [$start, $end] = slot('13:00');
    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();

    [$start, $end] = slot('14:00');
    expect(rules()->refusal(member(), rink('A-2'), $start, $end))->toBeNull();
});

it('ignores cancelled bookings when checking occupancy', function () {
    bookSlot(member('other@example.com'), '14:00')->update(['status' => 'cancelled']);

    [$start, $end] = slot('14:00');

    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();
});

it('refuses more players than the rink capacity', function () {
    [$start, $end] = slot('14:00');

    expect(rules()->refusal(member(), rink(), $start, $end, 3))->toBe('Too many players for this rink.')
        ->and(rules()->refusal(member(), rink(), $start, $end, 2))->toBeNull()
        ->and(rules()->refusal(member(), rink(), $start, $end, 0))->toBe('The number of players is invalid.');
});

it('allows one rink per member per day, staff exempt', function () {
    $user = member();
    bookSlot($user, '14:00', 1, 'A-1');

    // Another rink, same day
    [$start, $end] = slot('15:00');
    expect(rules()->refusal($user, rink('B-3'), $start, $end))->toBe('You already have a booking on this day.');

    // Another day
    [$start, $end] = slot('15:00', 2);
    expect(rules()->refusal($user, rink('B-3'), $start, $end))->toBeNull();

    // Staff book for members regardless
    $staff = staff();
    bookSlot($staff, '14:00', 1, 'A-2');
    [$start, $end] = slot('15:00');
    expect(rules()->refusal($staff, rink('B-3'), $start, $end))->toBeNull();
});

it('does not count a cancelled booking for one rink per day', function () {
    $user = member();
    bookSlot($user, '14:00')->update(['status' => 'cancelled']);

    [$start, $end] = slot('15:00');

    expect(rules()->refusal($user, rink('B-3'), $start, $end))->toBeNull();
});

it('blocks a rink, a green or all rinks with an event', function () {
    [$start, $end] = slot('14:00');

    $event = Event::query()->create([
        'sid' => rink('A-1')->sid,
        'status' => 'enabled',
        'datetime_start' => $start->subHour(),
        'datetime_end' => $end->addHour(),
    ]);
    $event->setMeta('name', 'Club competition');

    expect(rules()->refusal(member(), rink('A-1'), $start, $end))->toBe('This time is blocked by an event.')
        ->and(rules()->refusal(member(), rink('A-2'), $start, $end))->toBeNull();

    // Green event: sid null, meta green
    $event->update(['sid' => null]);
    $event->setMeta('green', 'A');

    expect(rules()->refusal(member(), rink('A-2'), $start, $end))->toBe('This time is blocked by an event.')
        ->and(rules()->refusal(member(), rink('B-1'), $start, $end))->toBeNull();

    // All rinks: sid null, no green
    $event->setMeta('green', null);

    expect(rules()->refusal(member(), rink('B-1'), $start, $end))->toBe('This time is blocked by an event.');
});

it('ignores disabled events', function () {
    [$start, $end] = slot('14:00');

    Event::query()->create([
        'sid' => null,
        'status' => 'disabled',
        'datetime_start' => $start->subHour(),
        'datetime_end' => $end->addHour(),
    ]);

    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();
});

it('refuses a rink on a closed green', function () {
    [$start, $end] = slot('14:00');

    app(Settings::class)->set('service.greens.closed', $start->format('Y-m-d').':A');

    expect(rules()->refusal(member(), rink('A-1'), $start, $end))->toBe('Green A is closed on this day.')
        ->and(rules()->refusal(member(), rink('B-1'), $start, $end))->toBeNull();
});

it('refuses hidden days, and lets a + entry re-allow one', function () {
    [$start, $end] = slot('14:00'); // a Tuesday

    app(Settings::class)->set('service.calendar.day-exceptions', 'Tuesday');

    expect(rules()->refusal(member(), rink(), $start, $end))->toBe('This day is not open for booking.');

    app(Settings::class)->set('service.calendar.day-exceptions', "Tuesday\n+".$start->format('Y-m-d'));

    expect(rules()->refusal(member(), rink(), $start, $end))->toBeNull();
});

it('refuses a disabled rink for members but not staff', function () {
    rink('A-1')->update(['status' => 'disabled']);

    [$start, $end] = slot('14:00');

    expect(rules()->refusal(member(), rink('A-1'), $start, $end))->toBe('This rink is currently not available.')
        ->and(rules()->refusal(staff(), rink('A-1'), $start, $end))->toBeNull();
});

it('limits open bookings when max_active_bookings is set', function () {
    $user = member();
    $user->setMeta('max_active_bookings', '1');

    bookSlot($user, '14:00', 1);

    [$start, $end] = slot('14:00', 2);

    expect(rules()->refusal($user, rink('A-2'), $start, $end))->toBe('You can only have 1 open booking(s) at the same time.');
});

it('lets the owner cancel before the cut-off only, staff any time', function () {
    $user = member();

    // Tomorrow 14:00, now Monday 13:00: more than 24 hours ahead
    $booking = bookSlot($user, '14:00', 1);
    expect(rules()->isCancellable($user, $booking))->toBeTrue();

    // Less than 24 hours ahead
    Carbon::setTestNow('2026-10-05 15:00');
    expect(rules()->isCancellable($user, $booking))->toBeFalse()
        ->and(rules()->isCancellable(staff(), $booking))->toBeTrue()
        ->and(rules()->isCancellable(member('other@example.com'), $booking))->toBeFalse()
        ->and(rules()->isCancellable(null, $booking))->toBeFalse();
});

it('does not let the owner cancel when the rink has no cancel range', function () {
    $user = member();
    rink('A-1')->update(['range_cancel' => 0]);

    $booking = bookSlot($user, '14:00', 5);

    expect(rules()->isCancellable($user, $booking))->toBeFalse();
});
