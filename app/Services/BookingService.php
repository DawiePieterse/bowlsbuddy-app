<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Makes and cancels bookings, applying the BookingRules.
 *
 * No double bookings: a booking is made inside a transaction that first locks the member's row, then the
 * rink's row (SELECT … FOR UPDATE), re-checks every rule and only then inserts. Two bookings of the same rink,
 * or by the same member (one rink per day, active booking limit), therefore run one after the other, and
 * the second one sees the first. Always locking the member before the rink keeps two bookings from waiting
 * on each other.
 */
class BookingService
{
    public function __construct(private readonly BookingRules $rules) {}

    /**
     * Books $rink for $user from $start to $end for $players players (the member and the others named in
     * $playerNames).
     *
     * @param  list<string>  $playerNames
     *
     * @throws BookingRefused
     */
    public function book(User $user, Rink $rink, CarbonInterface $start, CarbonInterface $end, int $players = 1, array $playerNames = []): Booking
    {
        return DB::transaction(function () use ($user, $rink, $start, $end, $players, $playerNames) {
            $user = User::query()->whereKey($user->uid)->lockForUpdate()->firstOrFail();
            $rink = Rink::query()->whereKey($rink->sid)->lockForUpdate()->firstOrFail();

            $refusal = $this->rules->refusal($rink, $start, $end, $user, $players);

            if ($refusal !== null) {
                throw new BookingRefused($refusal);
            }

            $booking = Booking::query()->create([
                'uid' => $user->uid,
                'sid' => $rink->sid,
                'status' => 'single',
                'visibility' => 'public',
                'quantity' => $players,
            ]);

            $booking->reservations()->create([
                'date' => $start->toDateString(),
                'time_start' => $start->format('H:i:s'),
                'time_end' => $end->format('H:i:s'),
            ]);

            $booking->setPlayerNames($playerNames);

            return $booking;
        });
    }

    /** @throws BookingRefused */
    public function cancel(Booking $booking, User $user): void
    {
        if (! $this->rules->canCancel($booking, $user)) {
            throw new BookingRefused(null, 'This booking can no longer be cancelled.');
        }

        $booking->update(['status' => 'cancelled']);
    }
}
