<?php

use App\Models\Booking;
use App\Services\BookingRefusal;
use App\Services\BookingRefused;
use App\Services\BookingService;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
});

it('books a slot with the other players named', function () {
    $member = member();

    [$start, $end] = slot('2026-10-06 14:00');

    $booking = app(BookingService::class)->book($member, rink('A-3'), $start, $end, 2, ['Piet Pompies']);

    $reservation = $booking->reservations()->sole();

    expect($booking->fresh())
        ->uid->toBe($member->uid)
        ->sid->toBe(rink('A-3')->sid)
        ->status->toBe('single')
        ->visibility->toBe('public')
        ->quantity->toBe(2)
        ->and($booking->fresh()->playerNames())->toBe(['Piet Pompies'])
        ->and($reservation->date->toDateString())->toBe('2026-10-06')
        ->and($reservation->time_start)->toBe('14:00:00')
        ->and($reservation->time_end)->toBe('15:00:00');
});

it('refuses a booking the rules do not allow, and saves nothing', function () {
    $member = member();
    app(BookingService::class)->book($member, rink('A-1'), ...slot('2026-10-06 12:00'));

    expect(fn () => app(BookingService::class)->book($member, rink('A-2'), ...slot('2026-10-06 13:00')))
        ->toThrow(function (BookingRefused $refused) {
            expect($refused->refusal)->toBe(BookingRefusal::OneRinkPerDay)
                ->and($refused->getMessage())->toBe(BookingRefusal::OneRinkPerDay->message());
        });

    expect(Booking::query()->count())->toBe(1);
});

it('cancels a booking before the cut-off only', function () {
    $member = member();
    $service = app(BookingService::class);
    $booking = $service->book($member, rink('A-1'), ...slot('2026-10-07 12:00'));

    $service->cancel($booking, $member);

    expect($booking->fresh()->status)->toBe('cancelled');

    $late = $service->book($member, rink('A-1'), ...slot('2026-10-06 12:00'));
    $this->travelTo(Carbon::parse('2026-10-05 13:00'));

    expect(fn () => $service->cancel($late, $member))->toThrow(BookingRefused::class)
        ->and($late->fresh()->status)->toBe('single');
});
