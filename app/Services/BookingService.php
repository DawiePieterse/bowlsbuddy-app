<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates and cancels bookings. A booking is created inside a transaction that first locks the
 * rink's and the member's rows (so two attempts for the same slot, or two rinks by the same
 * member on one day, run one after the other), then re-checks the rules and inserts the booking
 * with its reservation. No double bookings (PLAN.md 5.3).
 */
class BookingService
{
    public function __construct(private BookingRules $rules) {}

    /**
     * @param  list<string>  $playerNames
     *
     * @throws BookingRefused
     */
    public function create(
        User $user,
        Rink $rink,
        CarbonInterface $start,
        CarbonInterface $end,
        int $quantity = 1,
        array $playerNames = [],
        ?string $notes = null,
    ): Booking {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);

        return DB::transaction(function () use ($user, $rink, $start, $end, $quantity, $playerNames, $notes) {
            $rink = Rink::query()->whereKey($rink->sid)->lockForUpdate()->firstOrFail();
            User::query()->whereKey($user->uid)->lockForUpdate()->firstOrFail();

            $reason = $this->rules->refusal($user, $rink, $start, $end, $quantity);

            if ($reason !== null) {
                throw new BookingRefused($reason);
            }

            if ($end->diffInSeconds($start, true) < $rink->time_block_bookable) {
                $end = $start->addSeconds($rink->time_block_bookable);
            }

            $booking = Booking::query()->create([
                'uid' => $user->uid,
                'sid' => $rink->sid,
                'status' => 'single',
                'visibility' => 'public',
                'quantity' => $quantity,
            ]);

            $booking->reservations()->create([
                'date' => $start->format('Y-m-d'),
                'time_start' => $start->format('H:i:s'),
                'time_end' => $end->format('H:i:s'),
            ]);

            if ($playerNames) {
                $booking->setPlayerNames($playerNames);
            }

            if ($notes !== null && trim($notes) !== '') {
                $booking->setMeta('notes', trim($notes));
            }

            return $booking;
        });
    }

    /**
     * @throws BookingRefused
     */
    public function cancel(?User $user, Booking $booking): Booking
    {
        if (! $this->rules->isCancellable($user, $booking)) {
            throw new BookingRefused('This booking cannot be cancelled online anymore.');
        }

        $booking->update(['status' => 'cancelled']);

        return $booking;
    }
}
