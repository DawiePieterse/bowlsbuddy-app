<?php

use App\Filament\Pages\AffectedBookings;
use App\Filament\Pages\Greens;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Rinks\Pages\EditRink;
use App\Models\Booking;
use App\Models\User;
use App\Services\DisplacedBookings;
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
    $this->booking = booked($this->jan, 'A-1', '2026-10-06 12:00');
});

/** Closes green A on 6 Oct through the Secretary's button, as on the calendar. */
function closeGreenA(User $admin): void
{
    test()->actingAs($admin)->post('/greens/A/2026-10-06/close');
}

it('warns before closing a green that its bookings will be cancelled', function () {
    $this->actingAs($this->admin)->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Its 1 booking will be cancelled.');
});

it('cancels the bookings when the Secretary closes a green, and lists the members to message', function () {
    $this->actingAs($this->admin)
        ->post('/greens/A/2026-10-06/close')
        ->assertSessionHas('status', 'Green A is now closed on Tue 6 Oct and its 1 booking is cancelled. Let the members know below.');

    expect($this->booking->fresh()->status)->toBe('cancelled');

    $this->actingAs($this->admin)
        ->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Cancelled bookings')
        ->assertSee('Let 1 member know.')
        ->assertSee('Jan Botha')
        ->assertSee('082 123 4567')
        ->assertSee('Rink A-1 on Tue 6 Oct, 12:00–13:00: green A is closed that day')
        ->assertSee('https://wa.me/27821234567?text=Hi%20Jan%2C', false)
        ->assertSee('data-told="'.$this->booking->bid.'"', false)
        ->assertSee('/admin/affected-bookings', false);
});

it('says nothing about cancelling when nobody had booked', function () {
    $this->actingAs($this->admin)
        ->post('/greens/B/2026-10-06/close')
        ->assertSessionHas('status', 'Green B is now closed on Tue 6 Oct.');

    $this->actingAs($this->admin)->get('/greens/B/2026-10-06')->assertOk()->assertDontSee('Cancelled bookings');
});

it('keeps the bookings cancelled when the green opens again', function () {
    closeGreenA($this->admin);

    $this->actingAs($this->admin)
        ->post('/greens/A/2026-10-06/open')
        ->assertSessionHas('status', 'Green A is open again on Tue 6 Oct. The bookings cancelled when it closed stay cancelled.');

    expect($this->booking->fresh()->status)->toBe('cancelled');
});

it('lets the member book another green that day once theirs is closed', function () {
    closeGreenA($this->admin);

    $this->actingAs($this->jan)
        ->post('/book', ['rink' => rink('B-1')->sid, 'start' => '2026-10-06 12:00', 'players' => 1])
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('warning');

    expect(Booking::query()->where('uid', $this->jan->uid)->where('status', 'single')->count())->toBe(1);
});

it('tells the member why their booking was cancelled', function () {
    closeGreenA($this->admin);

    $this->actingAs($this->jan)->get('/bookings')
        ->assertOk()
        ->assertSee('cancelled because green A is closed that day');
});

it('keeps the list of members to message from members', function () {
    closeGreenA($this->admin);

    $this->actingAs(member())->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertDontSee('Jan Botha')
        ->assertDontSee('wa.me/27821234567', false);
});

it('notes a member as told when the Secretary sends the message or marks it', function () {
    closeGreenA($this->admin);

    $this->actingAs($this->admin)
        ->postJson('/affected/told', ['bookings' => (string) $this->booking->bid])
        ->assertNoContent();

    expect($this->booking->fresh()->meta(DisplacedBookings::TOLD))->toBe('1');

    $this->actingAs($this->admin)->get('/greens/A/2026-10-06')->assertSee('Everyone has been told.');
});

it('keeps members from marking anyone as told', function () {
    closeGreenA($this->admin);

    $this->actingAs(member())->post('/affected/told', ['bookings' => (string) $this->booking->bid])->assertForbidden();

    expect($this->booking->fresh()->meta(DisplacedBookings::TOLD))->toBeNull();
});

it('lists every cancelled booking for the Secretary in the admin panel, with a count in the menu', function () {
    closeGreenA($this->admin);
    $mike = member();
    booked($mike, 'B-2', '2026-10-07 14:00');
    blockedBy('green:B', '2026-10-07 12:00', '2026-10-07 17:00', 'Club league');
    app(DisplacedBookings::class)->cancelDisplaced();

    $this->actingAs($this->admin);

    expect(AffectedBookings::getNavigationBadge())->toBe('2');

    Livewire::test(AffectedBookings::class)
        ->assertOk()
        ->assertSee('Jan Botha')
        ->assertSee('green A is closed that day')
        ->assertSee('the rink is set aside for Club league')
        ->assertSee('Send on WhatsApp')
        ->assertSee('No cellphone number')
        ->call('markTold', (string) $this->booking->bid)
        ->assertSee('Already told');

    expect(AffectedBookings::getNavigationBadge())->toBe('1');
});

it('shows no badge and an empty list when nothing was cancelled', function () {
    $this->actingAs($this->admin);

    expect(AffectedBookings::getNavigationBadge())->toBeNull();

    Livewire::test(AffectedBookings::class)->assertSee('No upcoming bookings were cancelled by a closure.');
});

it('keeps the affected bookings page from staff who cannot close greens', function () {
    $this->actingAs(staff('admin.see-menu', 'admin.booking'))->get('/admin/affected-bookings')->assertForbidden();
});

it('shows the affected bookings page to staff who can close greens', function () {
    $this->actingAs(staff('admin.see-menu', 'admin.event'))->get('/admin/affected-bookings')->assertOk();
});

it('cancels the bookings a new event takes, and nudges the Secretary', function () {
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
        ->assertNotified('1 booking cancelled');

    expect($this->booking->fresh()->status)->toBe('cancelled')
        ->and($this->booking->fresh()->meta(DisplacedBookings::REASON))->toBe('the rink is set aside for Club league');
});

it('cancels the upcoming bookings on a green that is hidden', function () {
    $this->actingAs($this->admin);

    Livewire::test(Greens::class)
        ->callAction('hideGreen', ['green' => 'A', 'visibility' => 'hide'])
        ->assertNotified('1 booking cancelled');

    expect($this->booking->fresh()->status)->toBe('cancelled');
});

it('cancels the upcoming bookings on a rink taken out of use', function () {
    $this->actingAs($this->admin);

    Livewire::test(EditRink::class, ['record' => rink('A-1')->sid])
        ->fillForm(['status' => 'disabled'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('1 booking cancelled');

    expect($this->booking->fresh()->meta(DisplacedBookings::REASON))->toBe('rink A-1 is closed until further notice');
});

it('cancels the bookings on a weekday the club stops playing', function () {
    $this->actingAs($this->admin);

    // 6 Oct 2026 is a Tuesday
    Livewire::test(SiteSettings::class)
        ->fillForm([
            'client_name_full' => 'LCE Bowls Club',
            'client_name_short' => 'LCE',
            'activation' => 'manual',
            'playing_days' => ['Monday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
        ])
        ->call('save')
        ->assertNotified('1 booking cancelled');

    expect($this->booking->fresh()->meta(DisplacedBookings::REASON))->toBe('the club is closed that day');
});

it('leaves bookings alone when nothing is closed', function () {
    app(GreenService::class)->setClosed('B', Carbon::parse('2026-10-06'), true);
    $this->actingAs($this->admin);

    Livewire::test(Greens::class)
        ->callAction('hideGreen', ['green' => 'B', 'visibility' => 'hide'])
        ->assertNotNotified('1 booking cancelled');

    expect($this->booking->fresh()->status)->toBe('single');
});
