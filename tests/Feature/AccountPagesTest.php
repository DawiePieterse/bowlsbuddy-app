<?php

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);

    $this->member = User::factory()->create(['alias' => 'Jane Bowler', 'pw' => 'old-password', 'phone' => '+27821234567']);
    $this->member->setMeta('firstname', 'Jane');
    $this->member->setMeta('lastname', 'Bowler');
});

it('shows the account page', function () {
    $this->actingAs($this->member)
        ->get('/account')
        ->assertOk()
        ->assertSee('Jane Bowler')
        ->assertSee('082 123 4567')
        ->assertSee('Change cellphone number')
        ->assertSee('Change password')
        ->assertSee('Download my data')
        ->assertSee('Delete my account');
});

it('changes the cellphone number with the right current password only', function () {
    $this->actingAs($this->member)
        ->put('/account/phone', ['phone' => '073 555 1234', 'current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($this->member)
        ->put('/account/phone', ['phone' => '073 555 1234', 'current_password' => 'old-password'])
        ->assertSessionHasNoErrors();

    expect($this->member->refresh()->phone)->toBe('+27735551234');
});

it('refuses a number another account uses, and numbers that are not cellphones', function () {
    User::factory()->create(['phone' => '+27735551234']);

    $this->actingAs($this->member)
        ->put('/account/phone', ['phone' => '0735551234', 'current_password' => 'old-password'])
        ->assertSessionHasErrors('phone');

    $this->actingAs($this->member)
        ->put('/account/phone', ['phone' => '011 612 7200', 'current_password' => 'old-password'])
        ->assertSessionHasErrors('phone');
});

it('changes the password after checking the current one', function () {
    $this->actingAs($this->member)
        ->put('/account/password', [
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
            'current_password' => 'old-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-pass', $this->member->refresh()->pw))->toBeTrue();
});

it('downloads my data as JSON with bookings', function () {
    $booking = Booking::query()->create([
        'uid' => $this->member->uid,
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => 2,
    ]);
    $booking->reservations()->create(['date' => '2026-10-06', 'time_start' => '14:00:00', 'time_end' => '15:00:00']);
    $booking->setPlayerNames(['Pat Partner']);

    $this->actingAs($this->member)
        ->get('/account/data')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="my-data.json"')
        ->assertJsonPath('name', 'Jane Bowler')
        ->assertJsonPath('cellphone', '+27821234567')
        ->assertJsonPath('bookings.0.rink', 'A-1')
        ->assertJsonPath('bookings.0.player_names.0', 'Pat Partner')
        ->assertJsonPath('bookings.0.reservations.0.date', '2026-10-06');
});

it('deletes the account with its bookings', function () {
    $booking = Booking::query()->create([
        'uid' => $this->member->uid,
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => 1,
    ]);
    $booking->reservations()->create(['date' => '2026-10-06', 'time_start' => '14:00:00', 'time_end' => '15:00:00']);

    $this->actingAs($this->member)
        ->delete('/account', ['current_password' => 'wrong'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($this->member)
        ->delete('/account', ['current_password' => 'old-password'])
        ->assertRedirect(route('home'));

    expect(User::query()->find($this->member->uid))->toBeNull()
        ->and(Booking::query()->where('uid', $this->member->uid)->count())->toBe(0)
        ->and(auth()->check())->toBeFalse();
});
