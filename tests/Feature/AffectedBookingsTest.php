<?php

use App\Filament\Pages\AffectedBookings;
use App\Filament\Pages\Greens;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Models\User;
use App\Services\GreenService;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    $this->jan = User::factory()->create(['alias' => 'Jan Botha', 'phone' => '+27821234567']);
    $this->jan->setMeta('firstname', 'Jan');
    $this->jan->setMeta('lastname', 'Botha');
    booked($this->jan, 'A-1', '2026-10-06 12:00');
});

it('shows the Secretary whom to message right after closing a green', function () {
    $this->actingAs($this->admin)
        ->post('/greens/A/2026-10-06/close')
        ->assertSessionHas('status', 'Green A is now closed on Tue 6 Oct. 1 member had booked: let them know below.');

    $this->actingAs($this->admin)
        ->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Let 1 member know')
        ->assertSee('Jan Botha')
        ->assertSee('082 123 4567')
        ->assertSee('Rink A-1 on Tue 6 Oct, 12:00–13:00: green A is closed that day')
        ->assertSee('https://wa.me/27821234567?text=Hi%20Jan%2C', false)
        ->assertSee('/admin/affected-bookings', false);
});

it('says nothing about messages when nobody had booked', function () {
    $this->actingAs($this->admin)
        ->post('/greens/B/2026-10-06/close')
        ->assertSessionHas('status', 'Green B is now closed on Tue 6 Oct.');

    $this->actingAs($this->admin)->get('/greens/B/2026-10-06')->assertOk()->assertDontSee('know</h2>', false);
});

it('keeps the list of members to message from members', function () {
    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

    $this->actingAs(member())->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertDontSee('Jan Botha')
        ->assertDontSee('wa.me/27821234567', false);
});

it('lists every affected booking for the Secretary in the admin panel, with a count in the menu', function () {
    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);
    blockedBy('green:B', '2026-10-07 12:00', '2026-10-07 17:00', 'Club league');
    booked(member(), 'B-2', '2026-10-07 14:00');

    $this->actingAs($this->admin);

    expect(AffectedBookings::getNavigationBadge())->toBe('2');

    Livewire::test(AffectedBookings::class)
        ->assertOk()
        ->assertSee('Jan Botha')
        ->assertSee('green A is closed that day')
        ->assertSee('the rink is set aside for Club league')
        ->assertSee('Send on WhatsApp')
        ->assertSee('No cellphone number');
});

it('shows no badge and an empty list when nothing is affected', function () {
    $this->actingAs($this->admin);

    expect(AffectedBookings::getNavigationBadge())->toBeNull();

    Livewire::test(AffectedBookings::class)->assertSee('No upcoming bookings are affected');
});

it('keeps the affected bookings page from staff who cannot close greens', function () {
    $this->actingAs(staff('admin.see-menu', 'admin.booking'))->get('/admin/affected-bookings')->assertForbidden();
});

it('shows the affected bookings page to staff who can close greens', function () {
    $this->actingAs(staff('admin.see-menu', 'admin.event'))->get('/admin/affected-bookings')->assertOk();
});

it('nudges the Secretary when a new event takes booked rinks', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'name' => 'Club league',
            'datetime_start' => '2026-10-06 12:00',
            'datetime_end' => '2026-10-06 17:00',
            'scope' => 'green',
            'green' => 'A',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('1 member had already booked');
});

it('nudges the Secretary when hiding a green with upcoming bookings', function () {
    $this->actingAs($this->admin);

    Livewire::test(Greens::class)
        ->callAction('hideGreen', ['green' => 'A', 'visibility' => 'hide'])
        ->assertNotified('1 member had already booked');
});
