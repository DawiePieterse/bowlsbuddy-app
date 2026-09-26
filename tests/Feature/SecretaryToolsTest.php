<?php

use App\Filament\Pages\SiteSettings;
use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();
});

it('lets the Secretary close and reopen a green from the calendar', function () {
    $this->actingAs($this->admin)
        ->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Close green A on this day');

    $this->actingAs($this->admin)
        ->post('/greens/A/2026-10-06/close')
        ->assertRedirect(route('greens.show', ['A', '2026-10-06']));

    expect(app(GreenService::class)->isClosed('A', Carbon::parse('2026-10-06')))->toBeTrue();

    $this->actingAs($this->admin)
        ->get('/greens/A/2026-10-06')
        ->assertSee('Green A is closed on this day.')
        ->assertSee('Open green A on this day');

    $this->actingAs($this->admin)->post('/greens/A/2026-10-06/open');

    expect(app(GreenService::class)->isClosed('A', Carbon::parse('2026-10-06')))->toBeFalse();
});

it('keeps members from closing a green', function () {
    $this->actingAs(User::factory()->create())
        ->post('/greens/A/2026-10-06/close')
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get('/greens/A/2026-10-06')
        ->assertDontSee('Close green A');
});

it('shows the Secretary the WhatsApp invite on the overview', function () {
    $this->actingAs($this->admin)
        ->get('/')
        ->assertSee('Invite members via WhatsApp')
        ->assertSee('https://wa.me/?text=', false);

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertDontSee('Invite members via WhatsApp');
});

it('prints a day sheet with the bookings and a QR code', function () {
    $member = User::factory()->create(['alias' => 'Jane Bowler']);
    $member->setMeta('firstname', 'Jane');
    $member->setMeta('lastname', 'Bowler');

    $booking = Booking::query()->create([
        'uid' => $member->uid,
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => 1,
    ]);
    $booking->reservations()->create(['date' => '2026-10-06', 'time_start' => '14:00:00', 'time_end' => '15:00:00']);

    $this->actingAs($this->admin)
        ->get('/greens/A/2026-10-06/sheet')
        ->assertOk()
        ->assertSee('Day sheet')
        ->assertSee('Jane Bowler')
        ->assertSee('<svg', false)
        ->assertSee('Print this sheet');
});

it('asks guests to log in before the day sheet', function () {
    $this->get('/greens/A/2026-10-06/sheet')->assertRedirect('/login');
});

it('saves the settings and cleans the info page HTML', function () {
    $this->actingAs($this->admin);

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'client_name_full' => 'LCE Bowls Club',
            'client_name_short' => 'LCE',
            'activation' => 'manual',
            'day_exceptions' => 'Tuesday',
            'max_active_bookings' => '2',
            'info' => '<p>Welcome!</p><script>alert(1)</script>',
        ])
        ->call('save')
        ->assertNotified('Settings saved');

    $settings = app(Settings::class);

    expect($settings->get('service.user.activation'))->toBe('manual')
        ->and($settings->get('service.calendar.day-exceptions'))->toBe('Tuesday')
        ->and($settings->get('service.user.default.max_active_bookings'))->toBe('2')
        ->and($settings->get('service.info'))->toContain('Welcome!')
        ->and($settings->get('service.info'))->not->toContain('<script');
});

it('keeps staff without admin.config out of the settings page', function () {
    $assist = User::factory()->withStatus('assist')->create();
    $assist->setMeta('allow.admin.see-menu', 'true');

    $this->actingAs($assist->fresh())->get('/admin/site-settings')->assertForbidden();
});
