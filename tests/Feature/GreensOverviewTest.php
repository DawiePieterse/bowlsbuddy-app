<?php

use App\Services\BookingRules;
use App\Services\GreenService;
use App\Services\GreensOverview;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Rule 8: the greens overview. "Now" is Monday 5 October 2026, 13:30, so today's 12:00 and 13:00 slots
 * have started and 3 of each rink's 5 slots are left.
 */

beforeEach(function () {
    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 13:30'));
});

it('shows the next 14 days with free and total slots per green', function () {
    $days = app(GreensOverview::class)->days();

    expect($days)->toHaveCount(14)
        ->and($days[0]['date']->toDateString())->toBe('2026-10-05')
        ->and($days[13]['date']->toDateString())->toBe('2026-10-18')
        ->and($days[0]['free'])->toBe(['A' => 18, 'B' => 18])
        ->and($days[0]['slots'])->toBe(['A' => 30, 'B' => 30])
        ->and($days[1]['free'])->toBe(['A' => 30, 'B' => 30])
        ->and($days[1]['closed'])->toBe(['A' => false, 'B' => false])
        ->and($days[1]['events'])->toBe(['A' => [], 'B' => []]);
});

it('counts bookings, events and closed greens', function () {
    $member = member();
    booked($member, 'A-1', '2026-10-05 15:00');
    booked($member, 'B-2', '2026-10-05 16:00', visibility: 'private');
    booked($member, 'B-1', '2026-10-05 12:00');
    booked($member, 'A-2', '2026-10-06 12:00', status: 'cancelled');
    rink('B-3')->update(['capacity_heterogenic' => true]);
    booked($member, 'B-3', '2026-10-05 16:00');

    blockedBy('green:B', '2026-10-05 14:00', '2026-10-05 15:00', 'League');
    blockedBy(null, '2026-10-06 12:00', '2026-10-06 13:00', 'Club day');
    blockedBy('A-4', '2026-10-06 16:00', '2026-10-06 17:00', 'Coaching');
    blockedBy(null, '2026-10-07 12:00', '2026-10-07 17:00', 'Cancelled day', status: 'disabled');

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

    [$today, $tomorrow, $wednesday] = app(GreensOverview::class)->days();

    expect($today['free'])->toBe(['A' => 17, 'B' => 12])
        ->and($today['events'])->toBe(['A' => [], 'B' => ['League']])
        ->and($tomorrow['free'])->toBe(['A' => 23, 'B' => 24])
        ->and($tomorrow['slots'])->toBe(['A' => 30, 'B' => 30])
        ->and($tomorrow['events'])->toBe(['A' => ['Club day', 'Coaching'], 'B' => ['Club day']])
        ->and($tomorrow['closed'])->toBe(['A' => true, 'B' => false])
        ->and($wednesday['free'])->toBe(['A' => 30, 'B' => 30])
        ->and($wednesday['events'])->toBe(['A' => [], 'B' => []]);
});

it('leaves out hidden days', function () {
    app(Settings::class)->set(BookingRules::DAY_EXCEPTIONS_OPTION, "Sunday\n+2026-10-18");

    $dates = collect(app(GreensOverview::class)->days())->map(fn (array $day) => $day['date']->toDateString());

    expect($dates)->toHaveCount(13)
        ->and($dates)->not->toContain('2026-10-11')
        ->and($dates)->toContain('2026-10-18');
});

it('counts a slot free only when it can still be booked', function () {
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    booked(member(), 'A-1', '2026-10-06 12:00');
    blockedBy('B-1', '2026-10-06 13:00', '2026-10-06 14:00');

    $free = app(GreensOverview::class)->days()[0]['free'];
    $rules = app(BookingRules::class);
    $bookable = ['A' => 0, 'B' => 0];

    foreach (app(GreenService::class)->greens() as $green => $rinks) {
        foreach ($rinks as $rink) {
            foreach ([12, 13, 14, 15, 16] as $hour) {
                [$start, $end] = slot("2026-10-06 $hour:00");

                if ($rules->refusal($rink, $start, $end, null) === null) {
                    $bookable[$green]++;
                }
            }
        }
    }

    expect($free)->toBe($bookable)->and($free)->toBe(['A' => 29, 'B' => 29]);
});

it('uses the same few queries however many bookings there are', function () {
    foreach (['A-1', 'A-2', 'B-1', 'B-2'] as $rink) {
        booked(member(), $rink, '2026-10-06 12:00');
    }
    blockedBy(null, '2026-10-06 12:00', '2026-10-06 13:00');
    app(Settings::class)->get('warm-up');

    DB::enableQueryLog();
    app(GreensOverview::class)->days();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
});

it('no longer counts a slot once it has started', function () {
    $this->travelTo(Carbon::parse('2026-10-05 13:00'));

    expect(app(GreensOverview::class)->days()[0]['free'])->toBe(['A' => 18, 'B' => 18]);
});
