<?php

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use App\Services\BookingRefused;
use App\Services\BookingService;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
});

function bookingService(): BookingService
{
    return app(BookingService::class);
}

function aMember(string $email = 'member@example.com'): User
{
    return User::query()->firstOrCreate(
        ['email' => $email],
        ['alias' => 'Member', 'status' => 'enabled', 'pw' => 'a-good-password'],
    );
}

function aRink(string $name = 'A-1'): Rink
{
    return Rink::query()->where('name', $name)->firstOrFail();
}

it('books a slot with its reservation, players and notes', function () {
    $start = Carbon::parse('2026-10-06 14:00')->toImmutable();

    $booking = bookingService()->create(
        aMember(), aRink(), $start, $start->addHour(),
        quantity: 2, playerNames: ['Pat Partner'], notes: 'First roll-up',
    );

    expect($booking->status)->toBe('single')
        ->and($booking->quantity)->toBe(2)
        ->and($booking->playerNames())->toBe(['Pat Partner'])
        ->and($booking->meta('notes'))->toBe('First roll-up');

    $reservation = $booking->reservations()->firstOrFail();

    expect($reservation->date->format('Y-m-d'))->toBe('2026-10-06')
        ->and($reservation->time_start)->toBe('14:00:00')
        ->and($reservation->time_end)->toBe('15:00:00');
});

it('stretches a shorter range to a whole block', function () {
    $start = Carbon::parse('2026-10-06 14:00')->toImmutable();

    $booking = bookingService()->create(aMember(), aRink(), $start, $start->addMinutes(30));

    expect($booking->reservations()->firstOrFail()->time_end)->toBe('15:00:00');
});

it('refuses a booked slot with the reason and stores nothing', function () {
    $start = Carbon::parse('2026-10-06 14:00')->toImmutable();

    bookingService()->create(aMember('first@example.com'), aRink(), $start, $start->addHour());

    expect(fn () => bookingService()->create(aMember(), aRink(), $start, $start->addHour()))
        ->toThrow(BookingRefused::class, 'This rink is already booked for this time.')
        ->and(Booking::query()->count())->toBe(1);
});

it('cancels a booking for its owner before the cut-off', function () {
    $user = aMember();
    $start = Carbon::parse('2026-10-07 14:00')->toImmutable();

    $booking = bookingService()->create($user, aRink(), $start, $start->addHour());

    bookingService()->cancel($user, $booking);

    expect($booking->refresh()->status)->toBe('cancelled');
});

it('refuses a cancellation past the cut-off', function () {
    $user = aMember();
    $start = Carbon::parse('2026-10-06 14:00')->toImmutable();

    $booking = bookingService()->create($user, aRink(), $start, $start->addHour());

    Carbon::setTestNow('2026-10-06 13:00'); // an hour before the slot, cut-off is 24 hours

    expect(fn () => bookingService()->cancel($user, $booking))
        ->toThrow(BookingRefused::class)
        ->and($booking->refresh()->status)->toBe('single');
});

it('frees the slot for others after a cancellation', function () {
    $user = aMember();
    $start = Carbon::parse('2026-10-07 14:00')->toImmutable();

    $booking = bookingService()->create($user, aRink(), $start, $start->addHour());
    bookingService()->cancel($user, $booking);

    $other = bookingService()->create(aMember('other@example.com'), aRink(), $start, $start->addHour());

    expect($other->status)->toBe('single');
});
