<?php

use App\Services\DaySheet;
use App\Services\GreenService;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Rule 9: the Secretary's day sheet, rinks by hour with player names, events and closed greens.
 */

beforeEach(function () {
    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
});

it('lists every green with its hourly slots and rinks', function () {
    $sheet = app(DaySheet::class)->for(Carbon::parse('2026-10-06'));

    expect(array_keys($sheet))->toBe(['A', 'B'])
        ->and($sheet['A']['slots'])->toBe([[43200, 46800], [46800, 50400], [50400, 54000], [54000, 57600], [57600, 61200]])
        ->and(collect($sheet['B']['rinks'])->map(fn (array $row) => $row['rink']->name)->all())
        ->toBe(['B-1', 'B-2', 'B-3', 'B-4', 'B-5', 'B-6'])
        ->and($sheet['A']['rinks'][0]['cells'][0])->toBe(['bookings' => [], 'events' => []]);
});

it('puts bookings with their players and events in their slots', function () {
    $anna = member();
    $booking = booked($anna, 'A-1', '2026-10-06 13:00', players: 2);
    $booking->setPlayerNames(['Piet Pompies']);
    booked(member(), 'A-2', '2026-10-06 13:00', status: 'cancelled');
    booked(member(), 'A-1', '2026-10-07 13:00');
    blockedBy('green:B', '2026-10-06 00:00', '2026-10-06 13:30', 'League');
    blockedBy('B-1', '2026-10-06 12:00', '2026-10-06 13:00', 'Coaching', status: 'disabled');
    app(GreenService::class)->setClosed('B', Carbon::parse('2026-10-06'), true);

    DB::enableQueryLog();
    $sheet = app(DaySheet::class)->for(Carbon::parse('2026-10-06'));
    $queries = count(DB::getQueryLog());

    $a1 = $sheet['A']['rinks'][0]['cells'];
    $a2 = $sheet['A']['rinks'][1]['cells'];
    $b1 = $sheet['B']['rinks'][0]['cells'];

    expect($a1[1]['bookings'])->toHaveCount(1)
        ->and($a1[1]['bookings'][0]->user->alias)->toBe($anna->alias)
        ->and($a1[1]['bookings'][0]->playerNames())->toBe(['Piet Pompies'])
        ->and($a1[0]['bookings'])->toBe([])
        ->and($a2[1]['bookings'])->toBe([])
        ->and(array_column($b1, 'events'))->toBe([['League'], ['League'], [], [], []])
        ->and($sheet['A']['closed'])->toBeFalse()
        ->and($sheet['B']['closed'])->toBeTrue()
        ->and($queries)->toBeLessThanOrEqual(9);
});
