<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use App\Services\GreensOverview;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

/*
 * The greens overview reference values (docs/REFERENCE-RULES.md): LCE seed gives 5 slots per rink
 * and day (12:00-17:00, 60 minutes), 30 per green. "Now" is Monday 2026-10-05 13:00, so the
 * overview starts today; the assertions below look at tomorrow, a full day.
 */

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
});

function overviewDay(int $index = 1): array
{
    return app(GreensOverview::class)->days(14)[$index];
}

function bookTomorrow(string $rinkName, string $time, int $quantity = 1): void
{
    $user = User::query()->firstOrCreate(
        ['email' => 'member@example.com'],
        ['alias' => 'Member', 'status' => 'enabled', 'pw' => 'a-good-password'],
    );

    $booking = Booking::query()->create([
        'uid' => $user->uid,
        'sid' => Rink::query()->where('name', $rinkName)->firstOrFail()->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => $quantity,
    ]);

    $booking->reservations()->create([
        'date' => '2026-10-06',
        'time_start' => $time,
        'time_end' => Carbon::parse('2026-10-06 '.$time)->addHour()->format('H:i:s'),
    ]);
}

it('shows 14 playing days with 30 free slots per green on an empty day', function () {
    $days = app(GreensOverview::class)->days(14);

    expect($days)->toHaveCount(14)
        ->and($days[0]['date']->format('Y-m-d'))->toBe('2026-10-05')
        ->and($days[1]['greens'])->toBe([
            'A' => ['total' => 30, 'free' => 30, 'closed' => false, 'events' => []],
            'B' => ['total' => 30, 'free' => 30, 'closed' => false, 'events' => []],
        ]);
});

it('does not count slots already over today as free', function () {
    // At 13:00, the 12:00 slot is over on every rink; 13:00-17:00 remain
    expect(overviewDay(0)['greens']['A'])->toMatchArray(['total' => 30, 'free' => 24]);
});

it('counts a booking against its green', function () {
    bookTomorrow('A-1', '14:00:00');

    expect(overviewDay()['greens']['A'])->toMatchArray(['total' => 30, 'free' => 29])
        ->and(overviewDay()['greens']['B'])->toMatchArray(['total' => 30, 'free' => 30]);
});

it('shows a closed green with no free slots', function () {
    app(Settings::class)->set('service.greens.closed', '2026-10-06:A');

    expect(overviewDay()['greens']['A'])->toMatchArray(['total' => 30, 'free' => 0, 'closed' => true])
        ->and(overviewDay()['greens']['B'])->toMatchArray(['total' => 30, 'free' => 30, 'closed' => false]);
});

it('blocks one rink for a day with an event and names it', function () {
    $event = Event::query()->create([
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'enabled',
        'datetime_start' => '2026-10-06 12:00:00',
        'datetime_end' => '2026-10-06 17:00:00',
    ]);
    $event->setMeta('name', 'Club competition');

    expect(overviewDay()['greens']['A'])->toMatchArray(['total' => 30, 'free' => 25, 'events' => ['Club competition']])
        ->and(overviewDay()['greens']['B'])->toMatchArray(['free' => 30, 'events' => []]);
});

it('blocks a whole green or all rinks with a green or global event', function () {
    $event = Event::query()->create([
        'sid' => null,
        'status' => 'enabled',
        'datetime_start' => '2026-10-06 12:00:00',
        'datetime_end' => '2026-10-06 17:00:00',
    ]);
    $event->setMeta('name', 'Green A maintenance');
    $event->setMeta('green', 'A');

    expect(overviewDay()['greens']['A'])->toMatchArray(['free' => 0, 'events' => ['Green A maintenance']])
        ->and(overviewDay()['greens']['B'])->toMatchArray(['free' => 30, 'events' => []]);

    $event->setMeta('green', null);

    expect(overviewDay()['greens']['B'])->toMatchArray(['free' => 0, 'events' => ['Green A maintenance']]);
});

it('blocks only overlapped slots for a shorter event', function () {
    Event::query()->create([
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'enabled',
        'datetime_start' => '2026-10-06 13:30:00',
        'datetime_end' => '2026-10-06 14:30:00',
    ]);

    // The 13:00 and 14:00 slots on A-1 are touched
    expect(overviewDay()['greens']['A'])->toMatchArray(['total' => 30, 'free' => 28]);
});

it('ignores cancelled bookings', function () {
    bookTomorrow('A-1', '14:00:00');
    Booking::query()->latest('bid')->firstOrFail()->update(['status' => 'cancelled']);

    expect(overviewDay()['greens']['A'])->toMatchArray(['free' => 30]);
});

it('skips hidden days', function () {
    app(Settings::class)->set('service.calendar.day-exceptions', 'Tuesday');

    $days = app(GreensOverview::class)->days(14);

    expect($days)->toHaveCount(14)
        ->and(array_map(fn ($day) => $day['date']->format('D'), $days))->not->toContain('Tue');
});
