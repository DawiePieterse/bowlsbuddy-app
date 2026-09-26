<?php

use App\Models\Rink;
use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
});

it('groups the visible rinks by green, in natural order', function () {
    Rink::query()->create(rink('A-1')->only([
        'capacity', 'capacity_heterogenic', 'time_start', 'time_end', 'time_block', 'time_block_bookable',
    ]) + ['name' => 'A-10']);
    rink('B-6')->update(['status' => 'disabled']);

    $greens = app(GreenService::class)->greens();

    expect(array_keys($greens))->toBe(['A', 'B'])
        ->and($greens['A']->pluck('name')->all())->toBe(['A-1', 'A-2', 'A-3', 'A-4', 'A-5', 'A-6', 'A-10'])
        ->and($greens['B']->pluck('name')->all())->toBe(['B-1', 'B-2', 'B-3', 'B-4', 'B-5']);
});

it('opens and closes greens per day and stores them as the original app did', function () {
    $greens = app(GreenService::class);

    $greens->setClosed('B', Carbon::parse('2026-10-07'), true);
    $greens->setClosed('A', Carbon::parse('2026-10-06'), true);

    expect(app(Settings::class)->get(GreenService::CLOSED_OPTION))->toBe("2026-10-06:A\n2026-10-07:B")
        ->and($greens->isClosed('A', Carbon::parse('2026-10-06')))->toBeTrue()
        ->and($greens->isClosed('B', Carbon::parse('2026-10-06')))->toBeFalse()
        ->and($greens->isRinkClosed(rink('B-2'), Carbon::parse('2026-10-07')))->toBeTrue()
        ->and($greens->closedOn(['A', 'B'], Carbon::parse('2026-10-06')))->toBe(['A' => true, 'B' => false]);

    $greens->setClosed('A', Carbon::parse('2026-10-06'), false);

    expect(app(Settings::class)->get(GreenService::CLOSED_OPTION))->toBe('2026-10-07:B');
});

it('forgets closures of past days when a green is opened or closed', function () {
    app(Settings::class)->set(GreenService::CLOSED_OPTION, "2026-10-01:A\r\n2026-10-05:B\n");

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-09'), true);

    expect(app(Settings::class)->get(GreenService::CLOSED_OPTION))->toBe("2026-10-05:B\n2026-10-09:A");
});
