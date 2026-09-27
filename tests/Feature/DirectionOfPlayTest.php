<?php

use App\Models\Rink;
use App\Models\User;
use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 09:00');
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();
});

it('stores the direction of play per green and day, dropping past days', function () {
    $greens = app(GreenService::class);

    $greens->setDirection('A', Carbon::parse('2026-10-06'), 'NS');
    $greens->setDirection('B', Carbon::parse('2026-10-06'), 'EW');

    expect($greens->direction('A', Carbon::parse('2026-10-06')))->toBe('NS')
        ->and($greens->directionLabel('B', Carbon::parse('2026-10-06')))->toBe('East-West')
        ->and($greens->direction('A', Carbon::parse('2026-10-07')))->toBeNull();

    // Change and clear
    $greens->setDirection('A', Carbon::parse('2026-10-06'), 'EW');
    expect($greens->direction('A', Carbon::parse('2026-10-06')))->toBe('EW');

    $greens->setDirection('A', Carbon::parse('2026-10-06'), null);
    expect($greens->direction('A', Carbon::parse('2026-10-06')))->toBeNull();

    // Stale entries fall away on the next write
    app(Settings::class)->set(GreenService::DIRECTION_OPTION, "2026-10-01:A:NS\n2026-10-06:B:EW");
    $greens->setDirection('A', Carbon::parse('2026-10-08'), 'NS');

    expect(app(Settings::class)->get(GreenService::DIRECTION_OPTION))->toBe("2026-10-06:B:EW\n2026-10-08:A:NS");
});

it('lets the Secretary set the direction from the calendar, members not', function () {
    $this->actingAs($this->admin)
        ->post('/greens/A/2026-10-06/direction', ['direction' => 'NS'])
        ->assertRedirect(route('greens.show', ['A', '2026-10-06']));

    expect(app(GreenService::class)->direction('A', Carbon::parse('2026-10-06')))->toBe('NS');

    $this->actingAs($this->admin)
        ->post('/greens/A/2026-10-06/direction', ['direction' => 'sideways'])
        ->assertStatus(422);

    $this->actingAs(User::factory()->create())
        ->post('/greens/A/2026-10-06/direction', ['direction' => 'NS'])
        ->assertForbidden();
});

it('shows the direction to members on the overview, calendar, booking page and day sheet', function () {
    app(GreenService::class)->setDirection('A', Carbon::parse('2026-10-06'), 'NS');

    $member = User::factory()->create();

    $this->actingAs($member)->get('/')
        ->assertOk()
        ->assertSee('North-South');

    $this->actingAs($member)->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Direction of play')
        ->assertSee('North-South');

    $this->actingAs($member)
        ->get('/book?rink='.Rink::query()->where('name', 'A-1')->firstOrFail()->sid.'&start=2026-10-06 14:00')
        ->assertOk()
        ->assertSee('Direction of play')
        ->assertSee('North-South');

    $this->actingAs($this->admin)->get('/greens/A/2026-10-06/sheet')
        ->assertOk()
        ->assertSee('play North-South');

    // Green B has no direction indicated
    $this->actingAs($member)->get('/greens/B/2026-10-06')
        ->assertOk()
        ->assertDontSee('Direction of play');
});
