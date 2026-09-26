<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Rink;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The booking rules (PLAN.md 5.3, reference semantics in docs/REFERENCE-RULES.md). Every check
 * takes the member (or null for a guest) and answers with the reason the slot cannot be booked,
 * or null when it can. Staff exemptions follow the old app: "calendar.create-single-bookings"
 * frees a user from the window, one-rink-per-day and disabled-rink limits, "calendar.see-past"
 * from the past check, "calendar.cancel-single-bookings" from the cancel cut-off.
 */
class BookingRules
{
    public function __construct(
        private GreenService $greens,
        private Settings $settings,
    ) {}

    public function isBookable(?User $user, Rink $rink, CarbonInterface $start, CarbonInterface $end, int $quantity = 1): bool
    {
        return $this->refusal($user, $rink, $start, $end, $quantity) === null;
    }

    /**
     * Why this slot cannot be booked, or null when it can. A range shorter than the rink's
     * bookable block is stretched to it, as in the old app.
     */
    public function refusal(?User $user, Rink $rink, CarbonInterface $start, CarbonInterface $end, int $quantity = 1): ?string
    {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);
        $staff = $user !== null && $user->hasPrivilege('calendar.create-single-bookings');

        if ($rink->status === 'disabled' && ! $staff) {
            return 'This rink is currently not available.';
        }

        if ($start >= $end || ! $start->isSameDay($end)) {
            return 'The time range is invalid.';
        }

        /* Rule 1: within the rink's day and times, whole blocks, not past, within the window */

        if ($end->diffInSeconds($start, true) < $rink->time_block_bookable) {
            $end = $start->addSeconds($rink->time_block_bookable);
        }

        $dayStart = $start->setTimeFromTimeString($rink->time_start);
        $dayEnd = $start->setTimeFromTimeString($rink->time_end);

        if ($start < $dayStart || $end > $dayEnd) {
            return 'The time range is invalid.';
        }

        if ($rink->time_block_bookable_max && $end->diffInSeconds($start, true) > $rink->time_block_bookable_max && ! $staff) {
            return sprintf('You cannot book more than %d minutes at once.', round($rink->time_block_bookable_max / 60));
        }

        // A slot without a lead time stays bookable through the first half of a block (old app).
        if ($start < now()->subSeconds((int) ($rink->time_block_bookable / 2))
            && ! ($user !== null && $user->hasPrivilege('calendar.see-past'))) {
            return 'This time is already over.';
        }

        if ($rink->min_range_book && $start < now()->addSeconds($rink->min_range_book) && ! $staff) {
            return 'This date is too soon to book.';
        }

        if ($rink->range_book && $start > now()->addSeconds($rink->range_book) && ! $staff) {
            return 'This date is too far ahead to book.';
        }

        /* Rule 6: hidden days */

        if ($this->greens->isHiddenDay($start)) {
            return 'This day is not open for booking.';
        }

        /* Rule 4: closed greens */

        if ($this->greens->isClosed($rink->green(), $start)) {
            return sprintf('Green %s is closed on this day.', $rink->green());
        }

        /* Rule 5: events */

        if ($this->blockingEvent($rink, $start, $end) !== null) {
            return 'This time is blocked by an event.';
        }

        /* Rule 2: capacity */

        if ($quantity < 1) {
            return 'The number of players is invalid.';
        }

        $occupancy = $this->occupancy($rink, $start, $end);

        if ($occupancy > 0 && ! $rink->capacity_heterogenic) {
            return 'This rink is already booked for this time.';
        }

        if ($rink->capacity - $occupancy < $quantity) {
            return $occupancy > 0
                ? 'This rink is already booked for this time.'
                : 'Too many players for this rink.';
        }

        /* Rule 3: one rink per member per day */

        if ($user !== null && ! $staff && $this->hasBookingOn($user, $start)) {
            return 'You already have a booking on this day.';
        }

        /* Maximum open bookings, when a limit is set (LCE: none) */

        if ($user !== null && ! $staff) {
            $limit = $this->maxActiveBookings($user, $rink);

            if ($limit > 0 && $this->activeBookingCount($user) >= $limit) {
                return sprintf('You can only have %d open booking(s) at the same time.', $limit);
            }
        }

        return null;
    }

    /**
     * Rule 7: whether this user may cancel this booking now.
     */
    public function isCancellable(?User $user, Booking $booking): bool
    {
        if ($booking->status !== 'single') {
            return false;
        }

        if ($user !== null && $user->hasPrivilege('calendar.cancel-single-bookings')) {
            return true;
        }

        if ($user === null || $user->uid !== $booking->uid) {
            return false;
        }

        $rink = $booking->rink;

        if (! $rink || ! $rink->range_cancel) {
            return false;
        }

        $reservation = $booking->reservations()->orderBy('date')->orderBy('time_start')->first();

        if (! $reservation) {
            return true;
        }

        $starts = CarbonImmutable::parse($reservation->date->format('Y-m-d').' '.$reservation->time_start);

        return $starts > now()->addSeconds($rink->range_cancel);
    }

    /**
     * The first event blocking this rink in the range: on the rink itself, its green, or all rinks.
     */
    public function blockingEvent(Rink $rink, CarbonInterface $start, CarbonInterface $end): ?Event
    {
        return Event::query()
            ->where('status', 'enabled')
            ->where('datetime_start', '<', $end)
            ->where('datetime_end', '>', $start)
            ->orderBy('datetime_start')
            ->get()
            ->first(fn (Event $event) => $event->covers($rink));
    }

    /**
     * Players already on the rink in this range (public, not cancelled).
     */
    public function occupancy(Rink $rink, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.sid', $rink->sid)
            ->where('bs_bookings.visibility', 'public')
            ->where('bs_bookings.status', '!=', 'cancelled')
            ->where('date', $start->format('Y-m-d'))
            ->where('time_start', '<', $end->format('H:i:s'))
            ->where('time_end', '>', $start->format('H:i:s'))
            ->sum('bs_bookings.quantity');
    }

    private function hasBookingOn(User $user, CarbonInterface $date): bool
    {
        return Reservation::query()
            ->where('date', $date->format('Y-m-d'))
            ->whereHas('booking', fn ($query) => $query
                ->where('uid', $user->uid)
                ->where('status', '!=', 'cancelled'))
            ->exists();
    }

    /**
     * The user's limit of open bookings: their own, else the rink's, else the site default; 0 is none.
     */
    private function maxActiveBookings(User $user, Rink $rink): int
    {
        $limit = (int) $this->settings->get('service.user.default.max_active_bookings', '0');

        if ((int) $rink->max_active_bookings !== 0) {
            $limit = (int) $rink->max_active_bookings;
        }

        if ((int) $user->meta('max_active_bookings', '0') !== 0) {
            $limit = (int) $user->meta('max_active_bookings', '0');
        }

        return $limit;
    }

    private function activeBookingCount(User $user): int
    {
        return Reservation::query()
            ->where(fn ($query) => $query
                ->where('date', '>', now()->format('Y-m-d'))
                ->orWhere(fn ($today) => $today
                    ->where('date', now()->format('Y-m-d'))
                    ->where('time_start', '>', now()->format('H:i:s'))))
            ->whereHas('booking', fn ($query) => $query
                ->where('uid', $user->uid)
                ->where('status', '!=', 'cancelled'))
            ->count();
    }
}
