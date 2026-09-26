<?php

use App\Models\Rink;
use App\Models\User;
use App\Services\ClubSetup;
use App\Support\Settings;

it('shows the setup page only while the install is empty', function () {
    $this->get('/setup')->assertOk()->assertSee('Set up your club');
    $this->get('/')->assertRedirect(route('setup'));
});

it('creates the club from the setup page', function () {
    $this->post('/setup', [
        'name' => 'Sunset Bowls Club',
        'short_name' => 'SBC',
        'greens' => 'A,B,C',
        'rinks_per_green' => 4,
        'time_start' => '10:00',
        'time_end' => '16:00',
        'slot_minutes' => 60,
        'players_per_rink' => 2,
        'booking_range_days' => 7,
        'cancel_range_hours' => 12,
        'admin_email' => 'sec@sunset.example',
        'admin_password' => 'a-good-password',
    ])->assertRedirect(route('login'));

    expect(Rink::query()->count())->toBe(12)
        ->and(Rink::query()->orderBy('priority')->first()->name)->toBe('A-1')
        ->and(Rink::query()->orderByDesc('priority')->first()->name)->toBe('C-4')
        ->and(User::query()->where('email', 'sec@sunset.example')->firstOrFail()->status)->toBe('admin')
        ->and(app(Settings::class)->get('client.name.full'))->toBe('Sunset Bowls Club');

    // Once set up, the page is gone
    $this->get('/setup')->assertNotFound();
    $this->post('/setup', [])->assertNotFound();
});

it('creates the club from the command line', function () {
    $this->artisan('club:create')
        ->expectsQuestion('Club name', 'CLI Bowls Club')
        ->expectsQuestion('Short name', 'CLI')
        ->expectsQuestion('Greens (comma-separated letters)', 'A')
        ->expectsQuestion('Rinks per green', '2')
        ->expectsQuestion('First slot starts (HH:MM)', '12:00')
        ->expectsQuestion('Last slot ends (HH:MM)', '17:00')
        ->expectsQuestion('Slot length in minutes', '60')
        ->expectsQuestion('Players per rink', '2')
        ->expectsQuestion('Bookable ahead (days)', '14')
        ->expectsQuestion('Cancel cut-off (hours)', '24')
        ->expectsQuestion('Secretary email address', 'sec@cli.example')
        ->expectsConfirmation('Create CLI Bowls Club with 1 greens x 2 rinks?', 'yes')
        ->assertSuccessful();

    expect(Rink::query()->count())->toBe(2)
        ->and(User::query()->where('email', 'sec@cli.example')->exists())->toBeTrue();
});

it('refuses the command on an install that already has a club', function () {
    config(['club.admin_password' => 'a-good-password']);
    app(ClubSetup::class)->create(config('club'));

    $this->artisan('club:create')->assertFailed();
});
