<?php

use App\Filament\Pages\PlayDirection;
use App\Models\User;
use App\Support\GreenDirections;
use Database\Seeders\ClubSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
    $this->directions = app(GreenDirections::class);
});

it('has no direction of play until the Secretary sets one', function () {
    expect($this->directions->forDay(today()))->toBe(['A' => null, 'B' => null])
        ->and(GreenDirections::label(null))->toBe('Not set');
});

it('keeps a direction on the following days until it is changed', function () {
    $this->directions->set('A', '2026-10-01', 'north-south');
    $this->directions->set('B', '2026-10-01', 'east-west');
    $this->directions->set('A', '2026-10-05', 'east-west');

    expect($this->directions->forDay('2026-09-30'))->toBe(['A' => null, 'B' => null])
        ->and($this->directions->forDay('2026-10-01'))->toBe(['A' => 'north-south', 'B' => 'east-west'])
        ->and($this->directions->forDay('2026-10-04'))->toBe(['A' => 'north-south', 'B' => 'east-west'])
        ->and($this->directions->forDay('2026-10-05'))->toBe(['A' => 'east-west', 'B' => 'east-west']);
});

it('replaces the direction set earlier for the same day', function () {
    $this->directions->set('A', '2026-10-01', 'north-south');
    $this->directions->set('A', '2026-10-01', 'east-west');

    expect($this->directions->forDay('2026-10-01')['A'])->toBe('east-west')
        ->and(DB::table('bs_green_directions')->count())->toBe(1);
});

it('only accepts north-south and east-west', function () {
    expect(fn () => $this->directions->set('A', '2026-10-01', 'diagonal'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => DB::table('bs_green_directions')->insert(['green' => 'A', 'date' => '2026-10-01', 'direction' => 'up']))
        ->toThrow(QueryException::class);
});

it('shows the direction of play on the member site', function () {
    $this->directions->set('A', today(), 'north-south');

    $this->get('/')->assertOk()
        ->assertSee('Direction of play')
        ->assertSeeInOrder(['Green A', 'North/South', 'Green B', 'Not set']);
});

it('shows today\'s direction of play at the top of the admin pages', function () {
    $this->directions->set('B', today(), 'east-west');

    $this->actingAs(User::query()->firstOrFail())->get('/admin')->assertOk()
        ->assertSee('Direction of play today')
        ->assertSeeInOrder(['Green A', 'Not set', 'Green B', 'East/West']);
});

it('lets the Secretary set the direction of play for chosen greens', function () {
    $this->actingAs(User::query()->firstOrFail());
    $from = Carbon::today()->addDays(2)->toDateString();

    Livewire::test(PlayDirection::class)
        ->callAction('set', ['date' => $from, 'greens' => ['A', 'B'], 'direction' => 'east-west'])
        ->assertHasNoActionErrors()
        ->assertNotified('Direction of play saved');

    expect($this->directions->forDay(Carbon::today()->addDay()))->toBe(['A' => null, 'B' => null])
        ->and($this->directions->forDay($from))->toBe(['A' => 'east-west', 'B' => 'east-west']);
});

it('shows the next two weeks on the direction of play page', function () {
    $this->directions->set('A', today(), 'north-south');

    $this->actingAs(User::query()->firstOrFail())->get('/admin/play-direction')->assertOk()
        ->assertSee('Next two weeks')
        ->assertSee(today()->addDays(13)->format('D j M'));
});

it('keeps the direction of play page from staff without the events privilege', function () {
    $assist = User::factory()->withStatus('assist')->create();
    $assist->setMeta('allow.admin.see-menu', 'true');

    $this->actingAs($assist->fresh())->get('/admin/play-direction')->assertForbidden();
});

it('works out two weeks of directions in two queries', function () {
    $this->directions->set('A', '2026-10-01', 'north-south');
    $this->directions->set('A', '2026-10-08', 'east-west');

    DB::enableQueryLog();
    $days = $this->directions->forDays('2026-09-30', '2026-10-13');

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($days)->toHaveCount(14)
        ->and($days['2026-09-30']['A'])->toBeNull()
        ->and($days['2026-10-07']['A'])->toBe('north-south')
        ->and($days['2026-10-13']['A'])->toBe('east-west');
});
