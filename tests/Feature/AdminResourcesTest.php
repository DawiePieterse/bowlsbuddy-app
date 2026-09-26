<?php

use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Rinks\Pages\EditRink;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();
});

it('maps resource access to the old privileges', function (string $privilege, string $url) {
    $assist = User::factory()->withStatus('assist')->create();
    $assist->setMeta('allow.admin.see-menu', 'true');

    $this->actingAs($assist->fresh())->get($url)->assertForbidden();

    $assist->setMeta('allow.'.$privilege, 'true');

    $this->actingAs($assist->fresh())->get($url)->assertOk();
})->with([
    'members' => ['admin.user', '/admin/members'],
    'bookings' => ['admin.booking', '/admin/bookings'],
    'events' => ['admin.event', '/admin/events'],
    'rinks' => ['admin.config', '/admin/rinks'],
]);

it('creates a member with names, privileges and password', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateMember::class)
        ->fillForm([
            'firstname' => 'Alice',
            'lastname' => 'Assistant',
            'email' => 'alice@example.com',
            'status' => 'assist',
            'password' => 'a-good-password',
            'privileges' => ['admin.booking', 'calendar.see-data'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();

    expect($alice->alias)->toBe('Alice Assistant')
        ->and($alice->firstName())->toBe('Alice')
        ->and($alice->hasPrivilege('admin.booking'))->toBeTrue()
        ->and($alice->hasPrivilege('admin.event'))->toBeFalse()
        ->and(Hash::check('a-good-password', $alice->pw))->toBeTrue();
});

it('edits privileges without losing the password', function () {
    $assist = User::factory()->withStatus('assist')->create(['pw' => 'keep-this-pass']);
    $assist->setMeta('firstname', 'Bob');
    $assist->setMeta('lastname', 'Helper');
    $assist->setMeta('allow.admin.booking', 'true');

    $this->actingAs($this->admin);

    Livewire::test(EditMember::class, ['record' => $assist->uid])
        ->assertFormSet(['firstname' => 'Bob', 'privileges' => ['admin.booking']])
        ->fillForm(['privileges' => ['admin.event'], 'password' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    $assist = $assist->fresh();

    expect($assist->hasPrivilege('admin.event'))->toBeTrue()
        ->and($assist->hasPrivilege('admin.booking'))->toBeFalse()
        ->and(Hash::check('keep-this-pass', $assist->pw))->toBeTrue();
});

it('activates a member and sets a temporary password from the list', function () {
    $new = User::factory()->withStatus('disabled')->create(['pw' => 'first-password']);

    $this->actingAs($this->admin);

    Livewire::test(ListMembers::class)
        ->callTableAction('activate', $new)
        ->assertNotified();

    expect($new->fresh()->status)->toBe('enabled');

    Livewire::test(ListMembers::class)
        ->callTableAction('temporaryPassword', $new)
        ->assertNotified();

    expect(Hash::check('first-password', $new->fresh()->pw))->toBeFalse();
});

it('creates a booking for a member with its reservation', function () {
    $member = User::factory()->create();
    $rink = Rink::query()->where('name', 'A-1')->firstOrFail();

    $this->actingAs($this->admin);

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'uid' => $member->uid,
            'sid' => $rink->sid,
            'date' => '2026-10-06',
            'time_start' => '14:00',
            'time_end' => '15:00',
            'quantity' => 2,
            'status' => 'single',
            'player_names' => ['Pat Partner'],
            'notes' => 'Booked by the Secretary',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = Booking::query()->where('uid', $member->uid)->firstOrFail();
    $reservation = $booking->reservations()->firstOrFail();

    expect($booking->sid)->toBe($rink->sid)
        ->and($booking->playerNames())->toBe(['Pat Partner'])
        ->and($booking->meta('notes'))->toBe('Booked by the Secretary')
        ->and($reservation->date->format('Y-m-d'))->toBe('2026-10-06')
        ->and($reservation->time_start)->toBe('14:00:00');
});

it('creates events for a rink, a green or all rinks', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'name' => 'Green A maintenance',
            'datetime_start' => '2026-10-06 12:00',
            'datetime_end' => '2026-10-06 17:00',
            'scope' => 'green',
            'green' => 'A',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->latest('eid')->firstOrFail();

    expect($event->sid)->toBeNull()
        ->and($event->meta('green'))->toBe('A')
        ->and($event->meta('name'))->toBe('Green A maintenance');

    $rink = Rink::query()->where('name', 'B-2')->firstOrFail();

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'name' => 'Rink repair',
            'datetime_start' => '2026-10-07 12:00',
            'datetime_end' => '2026-10-07 17:00',
            'scope' => 'rink',
            'sid' => $rink->sid,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->latest('eid')->firstOrFail();

    expect($event->sid)->toBe($rink->sid)
        ->and($event->meta('green'))->toBeNull();
});

it('edits a rink in minutes, days and hours', function () {
    $rink = Rink::query()->where('name', 'A-1')->firstOrFail();

    $this->actingAs($this->admin);

    Livewire::test(EditRink::class, ['record' => $rink->sid])
        ->assertFormSet(['slot_minutes' => 60, 'booking_range_days' => 14, 'cancel_range_hours' => 24])
        ->fillForm(['slot_minutes' => 90, 'cancel_range_hours' => 12])
        ->call('save')
        ->assertHasNoFormErrors();

    $rink = $rink->fresh();

    expect($rink->time_block)->toBe(5400)
        ->and($rink->time_block_bookable)->toBe(5400)
        ->and($rink->range_cancel)->toBe(43200);
});
