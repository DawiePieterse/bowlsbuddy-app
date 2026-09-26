<?php

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);

    $this->member = User::factory()->create(['alias' => 'Jane Bowler']);
    $this->rink = Rink::query()->where('name', 'A-1')->firstOrFail();
});

it('requires a login to book', function () {
    $this->get('/book?rink='.$this->rink->sid.'&start=2026-10-06 14:00')
        ->assertRedirect('/login');
});

it('shows the booking form for a free slot', function () {
    $this->actingAs($this->member)
        ->get('/book?rink='.$this->rink->sid.'&start=2026-10-06 14:00')
        ->assertOk()
        ->assertSee('Book rink A-1')
        ->assertSee('Tuesday 6 October 2026')
        ->assertSee('14:00')
        ->assertSee("partner's full name", false)
        ->assertSee('One rink per member per day')
        ->assertSee('24 hours before the start');
});

it('shows the refusal instead of the form on an unbookable slot', function () {
    pageFlowBook($this->member, '2026-10-06');

    $other = User::factory()->create();

    $this->actingAs($other)
        ->get('/book?rink='.$this->rink->sid.'&start=2026-10-06 14:00')
        ->assertOk()
        ->assertSee('This rink is already occupied.')
        ->assertDontSee('Book this rink');
});

function pageFlowBook(User $user, string $date): Booking
{
    $booking = Booking::query()->create([
        'uid' => $user->uid,
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => 1,
    ]);

    $booking->reservations()->create(['date' => $date, 'time_start' => '14:00:00', 'time_end' => '15:00:00']);

    return $booking;
}

it('books a slot for two players with the partner name', function () {
    $response = $this->actingAs($this->member)->post('/book', [
        'rink' => $this->rink->sid,
        'start' => '2026-10-06 14:00',
        'players' => 2,
        'partner' => 'Pat Partner',
    ]);

    $booking = Booking::query()->where('uid', $this->member->uid)->firstOrFail();

    $response->assertRedirect(route('bookings.confirmation', $booking));

    expect($booking->quantity)->toBe(2)
        ->and($booking->playerNames())->toBe(['Pat Partner'])
        ->and($booking->reservations()->count())->toBe(1);
});

it('books a single player without a partner', function () {
    $this->actingAs($this->member)->post('/book', [
        'rink' => $this->rink->sid,
        'start' => '2026-10-06 14:00',
        'players' => 1,
    ]);

    expect(Booking::query()->where('uid', $this->member->uid)->firstOrFail()->quantity)->toBe(1);
});

it('requires the full partner name for two players', function (array $input) {
    $this->actingAs($this->member)
        ->from('/book?rink='.$this->rink->sid.'&start=2026-10-06 14:00')
        ->post('/book', array_merge([
            'rink' => $this->rink->sid, 'start' => '2026-10-06 14:00', 'players' => 2,
        ], $input))
        ->assertSessionHasErrors('partner');

    expect(Booking::query()->count())->toBe(0);
})->with([
    'missing' => [[]],
    'first name only' => [['partner' => 'Pat']],
    'too short' => [['partner' => 'P P']],
]);

it('requires accepting the rink rules when the rink has some', function () {
    $this->rink->setMeta('rules.text', '<p>Wear flat shoes.</p>');

    $this->actingAs($this->member)
        ->get('/book?rink='.$this->rink->sid.'&start=2026-10-06 14:00')
        ->assertSee('Wear flat shoes.');

    $this->actingAs($this->member)
        ->post('/book', [
            'rink' => $this->rink->sid, 'start' => '2026-10-06 14:00', 'players' => 1,
        ])
        ->assertSessionHasErrors('accept_rules');

    $this->actingAs($this->member)
        ->post('/book', [
            'rink' => $this->rink->sid, 'start' => '2026-10-06 14:00', 'players' => 1, 'accept_rules' => 1,
        ])
        ->assertSessionHasNoErrors();
});

it('turns a second rink on the same day into a friendly warning', function () {
    pageFlowBook($this->member, '2026-10-06');

    $this->actingAs($this->member)
        ->from('/book?rink='.$this->rink->sid.'&start=2026-10-06 15:00')
        ->post('/book', [
            'rink' => Rink::query()->where('name', 'B-1')->firstOrFail()->sid,
            'start' => '2026-10-06 15:00',
            'players' => 1,
        ])
        ->assertRedirect('/book?rink='.$this->rink->sid.'&start=2026-10-06 15:00')
        ->assertSessionHas('warning', 'You can only book one rink per day. You already have a booking on this day.');

    expect(Booking::query()->count())->toBe(1);
});

it('shows the confirmation with a WhatsApp share link to the owner only', function () {
    $this->actingAs($this->member)->post('/book', [
        'rink' => $this->rink->sid, 'start' => '2026-10-06 14:00', 'players' => 2, 'partner' => 'Pat Partner',
    ]);

    $booking = Booking::query()->firstOrFail();

    $this->actingAs($this->member)
        ->get(route('bookings.confirmation', $booking))
        ->assertOk()
        ->assertSee('Rink A-1 is yours')
        ->assertSee('https://wa.me/?text=', false)
        ->assertSee('Pat%20Partner', false)
        ->assertSee('Share on WhatsApp');

    $this->actingAs(User::factory()->create())
        ->get(route('bookings.confirmation', $booking))
        ->assertForbidden();
});

it('lists bookings with a cancel button inside the cut-off', function () {
    $booking = pageFlowBook($this->member, '2026-10-07'); // Wednesday, > 24 h away

    $this->actingAs($this->member)
        ->get('/bookings')
        ->assertOk()
        ->assertSee('Wed 7 Oct 2026')
        ->assertSee('Rink A-1')
        ->assertSee('Cancel');

    $this->actingAs($this->member)
        ->post(route('bookings.cancel', $booking))
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($booking->refresh()->status)->toBe('cancelled');

    $this->actingAs($this->member)->get('/bookings')->assertSee('cancelled');
});

it('refuses to cancel past the cut-off but keeps staff able to', function () {
    $booking = pageFlowBook($this->member, '2026-10-05'); // today 14:00, only an hour away, cut-off 24 h

    $this->actingAs($this->member)
        ->post(route('bookings.cancel', $booking))
        ->assertSessionHas('warning');

    expect($booking->refresh()->status)->toBe('single');

    $admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    $this->actingAs($admin)->post(route('bookings.cancel', $booking));

    expect($booking->refresh()->status)->toBe('cancelled');
});

it('keeps other members from cancelling a booking', function () {
    $booking = pageFlowBook($this->member, '2026-10-07');

    $this->actingAs(User::factory()->create())
        ->post(route('bookings.cancel', $booking))
        ->assertForbidden();
});
