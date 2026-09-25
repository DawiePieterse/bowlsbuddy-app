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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Whether a member may book a rink for a time, and cancel a booking. Ported from the original app's
 * Square\Service\SquareValidator (isValid, isBookable, isCancellable) and the quantity check in
 * Square\Controller\BookingController. See docs/PLAN.md, section 5.3.
 *
 * Staff with the calendar.create-single-bookings privilege may book disabled rinks, at short notice, far
 * ahead, many slots at once and more than one rink per day, as in the original.
 */
class BookingRules
{
    public const DAY_EXCEPTIONS_OPTION = 'service.calendar.day-exceptions';

    public const MAX_ACTIVE_BOOKINGS_OPTION = 'service.user.default.max_active_bookings';

    public function __construct(
        private readonly Settings $settings,
        private readonly GreenService $greens,
    ) {}

    /**
     * Why $user (null for a visitor) can't book $players players on $rink from $start to $end, or null if
     * they can.
     */
    public function refusal(Rink $rink, CarbonInterface $start, CarbonInterface $end, ?User $user, int $players = 1): ?BookingRefusal
    {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);
        $staff = $user?->hasPrivilege('calendar.create-single-bookings') ?? false;

        return $this->invalid($rink, $start, $end, $user, $staff)
            ?? $this->unavailable($rink, $start, $end, $user, $staff, $players);
    }

    /**
     * Players already booked on the rink during the time (active, public bookings only).
     */
    public function playersBooked(Rink $rink, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) Booking::query()
            ->where('sid', $rink->sid)
            ->where('status', '!=', 'cancelled')
            ->where('visibility', 'public')
            ->whereHas('reservations', fn (Builder $query) => $query
                ->where('date', $start->toDateString())
                ->where('time_start', '<', $end->format('H:i:s'))
                ->where('time_end', '>', $start->format('H:i:s')))
            ->sum('quantity');
    }

    /**
     * Staff with calendar.cancel-single-bookings may cancel any booking. Members may cancel their own until
     * range_cancel seconds before it starts; with no range_cancel set on the rink, not at all.
     */
    public function canCancel(Booking $booking, ?User $user): bool
    {
        if ($user === null || $booking->status !== 'single') {
            return false;
        }

        if ($user->hasPrivilege('calendar.cancel-single-bookings')) {
            return true;
        }

        if ((int) $booking->uid !== (int) $user->uid) {
            return false;
        }

        $rangeCancel = (int) $booking->rink->range_cancel;

        if ($rangeCancel === 0) {
            return false;
        }

        $first = $booking->reservations()->orderBy('date')->orderBy('time_start')->first();

        if ($first === null) {
            return true;
        }

        return $this->startOf($first)->greaterThan(Carbon::now()->addSeconds($rangeCancel));
    }

    /**
     * Days hidden by the service.calendar.day-exceptions setting: lines or commas with a weekday name
     * ("Sunday") or a date ("2026-12-25") hide a day; "+2026-12-27" shows that date anyway.
     */
    public function isDayHidden(CarbonInterface $date): bool
    {
        $hidden = false;
        $shown = false;

        foreach (preg_split('~[\n,]~', (string) $this->settings->get(self::DAY_EXCEPTIONS_OPTION, '')) ?: [] as $exception) {
            $exception = trim($exception);

            if ($exception === '') {
                continue;
            }

            if ($exception[0] === '+') {
                $shown = $shown || trim($exception, '+ ') === $date->toDateString();
            } elseif ($exception === $date->toDateString() || strcasecmp($exception, $date->englishDayOfWeek) === 0) {
                $hidden = true;
            }
        }

        return $hidden && ! $shown;
    }

    /** Checks on the rink and the time itself (SquareValidator::isValid). */
    private function invalid(Rink $rink, CarbonImmutable $start, CarbonImmutable $end, ?User $user, bool $staff): ?BookingRefusal
    {
        if (in_array($rink->status, ['disabled', 'readonly'], true) && ! $staff) {
            return BookingRefusal::RinkUnavailable;
        }

        if ($start->greaterThanOrEqualTo($end) || ! $start->isSameDay($end)) {
            return BookingRefusal::InvalidTime;
        }

        $day = $start->startOfDay();
        $opens = $day->addSeconds(self::seconds($rink->time_start));
        $closes = $day->addSeconds(self::seconds($rink->time_end));

        if ($start->lessThan($opens) || $end->greaterThan($closes)) {
            return BookingRefusal::OutsidePlayingHours;
        }

        // Whole slots only (the original app left this to the calendar page).
        $block = max(60, $rink->time_block);
        $length = (int) $start->diffInSeconds($end);

        if ((int) $opens->diffInSeconds($start) % $block !== 0 || $length % $block !== 0
            || $length < $rink->time_block_bookable) {
            return BookingRefusal::InvalidTime;
        }

        $now = CarbonImmutable::now();
        $minRange = (int) $rink->min_range_book;
        $earliest = $minRange === 0 ? $now->subSeconds(intdiv($rink->time_block_bookable, 2)) : $now->addSeconds($minRange);

        if ($start->lessThan($earliest)) {
            $seesPast = $user !== null && ($user->hasPrivilege('calendar.see-past')
                || ($user->hasPrivilege('calendar.see-data') && $start->isSameDay($earliest)));

            if (! $seesPast) {
                return $minRange > 0 && $start->greaterThanOrEqualTo($now)
                    ? BookingRefusal::TooShortNotice
                    : BookingRefusal::InThePast;
            }

            if ($minRange > 0 && ! $staff) {
                return BookingRefusal::TooShortNotice;
            }
        }

        if ($rink->range_book && $start->greaterThan($now->addSeconds($rink->range_book)) && ! $staff) {
            return BookingRefusal::TooFarAhead;
        }

        if ($rink->time_block_bookable_max && $length > $rink->time_block_bookable_max && ! $staff) {
            return BookingRefusal::TooLong;
        }

        if ($this->isDayHidden($start)) {
            return BookingRefusal::DayHidden;
        }

        return null;
    }

    /**
     * Checks on what else is booked (SquareValidator::isBookable). The order decides which reason is shown
     * when several apply, matching the message the original app shows.
     */
    private function unavailable(Rink $rink, CarbonImmutable $start, CarbonImmutable $end, ?User $user, bool $staff, int $players): ?BookingRefusal
    {
        if ($this->greens->isRinkClosed($rink, $start)) {
            return BookingRefusal::GreenClosed;
        }

        if ($user !== null && $this->hasMaxActiveBookings($rink, $user)) {
            return BookingRefusal::MaxActiveBookings;
        }

        $booked = $this->playersBooked($rink, $start, $end);

        if ($booked >= $rink->capacity || ($booked > 0 && ! $rink->capacity_heterogenic)) {
            return BookingRefusal::Occupied;
        }

        if ($user !== null && ! $staff && $this->hasBookingOn($user, $start)) {
            return BookingRefusal::OneRinkPerDay;
        }

        if ($this->hasEvent($rink, $start, $end)) {
            return BookingRefusal::Event;
        }

        if ($players < 1) {
            return BookingRefusal::InvalidPlayers;
        }

        if ($rink->capacity - $booked < $players) {
            return BookingRefusal::TooManyPlayers;
        }

        return null;
    }

    /**
     * The limit is the user's meta max_active_bookings, else the rink's, else the club default; 0 is no
     * limit. Counts reservations that haven't started, on any rink.
     */
    private function hasMaxActiveBookings(Rink $rink, User $user): bool
    {
        $limit = (int) $user->meta('max_active_bookings', '0')
            ?: (int) $rink->max_active_bookings
            ?: (int) $this->settings->get(self::MAX_ACTIVE_BOOKINGS_OPTION, '0');

        if ($limit === 0) {
            return false;
        }

        $now = Carbon::now();

        $active = Reservation::query()
            ->whereHas('booking', fn (Builder $query) => $query
                ->where('uid', $user->uid)
                ->where('status', '!=', 'cancelled')
                ->where('visibility', 'public'))
            ->where(fn (Builder $query) => $query
                ->where('date', '>', $now->toDateString())
                ->orWhere(fn (Builder $query) => $query
                    ->where('date', $now->toDateString())
                    ->where('time_start', '>', $now->format('H:i:s'))))
            ->count();

        return $active >= $limit;
    }

    /** Whether the user has a booking that isn't cancelled on that day, on any rink. */
    private function hasBookingOn(User $user, CarbonInterface $day): bool
    {
        return Booking::query()
            ->where('uid', $user->uid)
            ->where('status', '!=', 'cancelled')
            ->whereHas('reservations', fn (Builder $query) => $query->where('date', $day->toDateString()))
            ->exists();
    }

    private function hasEvent(Rink $rink, CarbonInterface $start, CarbonInterface $end): bool
    {
        return Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_end', '>', $start)
            ->where('datetime_start', '<', $end)
            ->get()
            ->contains(fn (Event $event) => $event->covers($rink));
    }

    private function startOf(Reservation $reservation): Carbon
    {
        return $reservation->date->copy()->addSeconds(self::seconds($reservation->time_start));
    }

    /** Seconds since midnight of a "HH:MM" or "HH:MM:SS" time. */
    public static function seconds(string $time): int
    {
        $parts = array_map('intval', explode(':', $time));

        return $parts[0] * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0);
    }
}
