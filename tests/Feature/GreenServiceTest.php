<?php

use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00'); // a Monday
    $this->seed(ClubSeeder::class);
});

function greens(): GreenService
{
    return app(GreenService::class);
}

it('lists the greens from the rink names', function () {
    expect(greens()->greens())->toBe(['A', 'B']);
});

it('closes and reopens a green per day', function () {
    $service = greens();
    $day = Carbon::parse('2026-10-06');

    expect($service->isClosed('A', $day))->toBeFalse();

    $service->close('A', $day);

    expect($service->isClosed('A', $day))->toBeTrue()
        ->and($service->isClosed('B', $day))->toBeFalse()
        ->and($service->isClosed('A', $day->copy()->addDay()))->toBeFalse()
        ->and(app(Settings::class)->get('service.greens.closed'))->toBe('2026-10-06:A');

    $service->close('B', $day);
    $service->close('A', $day); // twice is once

    expect($service->closedGreens($day))->toBe(['A', 'B'])
        ->and(app(Settings::class)->get('service.greens.closed'))->toBe("2026-10-06:A\n2026-10-06:B");

    $service->open('A', $day);

    expect($service->isClosed('A', $day))->toBeFalse()
        ->and($service->isClosed('B', $day))->toBeTrue();
});

it('reads closed greens the setting stores, in either separator', function () {
    app(Settings::class)->set('service.greens.closed', "2026-10-06:A,2026-10-07:B\n2026-10-06:B");

    expect(greens()->closedGreens(Carbon::parse('2026-10-06')))->toBe(['A', 'B'])
        ->and(greens()->closedGreens(Carbon::parse('2026-10-07')))->toBe(['B']);
});

it('hides weekdays and dates, with + entries as exceptions', function () {
    $service = greens();
    app(Settings::class)->set('service.calendar.day-exceptions', "Tuesday, 2026-10-08\n+2026-10-13");

    expect($service->isHiddenDay(Carbon::parse('2026-10-06')))->toBeTrue()   // a Tuesday
        ->and($service->isHiddenDay(Carbon::parse('2026-10-08')))->toBeTrue()  // the date
        ->and($service->isHiddenDay(Carbon::parse('2026-10-07')))->toBeFalse()
        ->and($service->isHiddenDay(Carbon::parse('2026-10-13')))->toBeFalse(); // Tuesday, but re-allowed
});

it('lists the next playing days, skipping hidden ones', function () {
    app(Settings::class)->set('service.calendar.day-exceptions', 'Tuesday,Thursday,Saturday,Sunday');

    $days = greens()->playingDays(4);

    expect(array_map(fn ($day) => $day->format('Y-m-d D'), $days))
        ->toBe(['2026-10-05 Mon', '2026-10-07 Wed', '2026-10-09 Fri', '2026-10-12 Mon']);
});

it('gives up on an exception list hiding every day', function () {
    app(Settings::class)->set(
        'service.calendar.day-exceptions',
        'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
    );

    expect(greens()->playingDays(14))->toBe([]);
});
