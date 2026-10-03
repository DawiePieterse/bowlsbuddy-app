<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Meta\BookingMeta;
use App\Models\Reservation;
use App\Models\Rink;
use App\Models\User;
use App\Support\Settings;
use App\Support\WhatsApp;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Upcoming bookings that can't go ahead: the club is closed that day, the rink was taken out of use (a hidden
 * green), its green was closed for the day, or an event took the rink. Every change that can close something
 * calls cancelDisplaced(), so those bookings are cancelled at once and the members can book another rink (a
 * live booking would still count as their one rink that day). Reopening a green does not bring them back.
 *
 * The reason is kept on the booking, and the Secretary gets one WhatsApp message per member to send from
 * their own phone (the app itself sends nothing); marking it as told clears it from the admin menu's badge.
 *
 * @phpstan-type Displaced array{booking: Booking, start: CarbonImmutable, end: CarbonImmutable, reason: string, told: bool}
 * @phpstan-type Message array{user: User, name: string, phone: string|null, items: list<Displaced>, bookings: string, told: bool, text: string, url: string|null}
 */
class DisplacedBookings
{
    /** Booking meta: why a closure cancelled the booking, as the end of "...because <reason>". */
    public const REASON = 'closure';

    /** Booking meta: "1" once the Secretary has let the member know. */
    public const TOLD = 'closure_told';

    public function __construct(
        private readonly GreenService $greens,
        private readonly BookingRules $rules,
        private readonly Settings $settings,
    ) {}

    /**
     * Live bookings that can't go ahead, soonest first; only those on one green and one day when given.
     *
     * @return list<Displaced>
     */
    public function upcoming(?string $green = null, ?CarbonInterface $day = null): array
    {
        $now = CarbonImmutable::now();

        $events = Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_end', '>', $now)
            ->orderBy('datetime_start')
            ->get();

        $displaced = [];

        foreach ($this->slots(cancelled: false, green: $green, day: $day) as $item) {
            $reason = $this->reason($item['booking']->rink, $item['start'], $item['end'], $events);

            if ($reason !== null) {
                $displaced[] = [...$item, 'reason' => $reason];
            }
        }

        return $displaced;
    }

    /**
     * Cancels the live bookings a closure or event displaces (only those on one green and one day when given),
     * noting why on each booking.
     *
     * @return list<Displaced> the bookings it cancelled
     */
    public function cancelDisplaced(?string $green = null, ?CarbonInterface $day = null): array
    {
        $displaced = $this->upcoming($green, $day);
        $ids = array_map(fn (array $item) => $item['booking']->bid, $displaced);

        DB::transaction(function () use ($displaced, $ids) {
            Booking::query()->whereIn('bid', $ids)->update(['status' => 'cancelled']);
            BookingMeta::query()->whereIn('bid', $ids)->where('key', self::TOLD)->delete();

            foreach ($displaced as $item) {
                $item['booking']->setMeta(self::REASON, $item['reason']);
            }
        });

        return $displaced;
    }

    /**
     * Upcoming bookings a closure cancelled, soonest first; only those on one green and one day when given.
     *
     * @return list<Displaced>
     */
    public function cancelled(?string $green = null, ?CarbonInterface $day = null): array
    {
        return array_map(
            fn (array $item) => [...$item, 'reason' => (string) $item['booking']->meta(self::REASON)],
            $this->slots(cancelled: true, green: $green, day: $day),
        );
    }

    /**
     * How many live bookings on these rinks that day haven't started yet (for the Secretary's close warning).
     *
     * @param  list<int>  $rinkIds
     */
    public function bookedCount(array $rinkIds, CarbonInterface $day): int
    {
        return Reservation::query()
            ->where('date', $day->toDateString())
            ->where(fn (Builder $query) => $this->notStarted($query))
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('sid', $rinkIds)->where('status', '!=', 'cancelled'))
            ->count();
    }

    /**
     * One message per member, listing their cancelled bookings, in the order of their first one.
     *
     * @param  list<Displaced>|null  $items  defaults to every upcoming booking a closure cancelled
     * @return list<Message>
     */
    public function messages(?array $items = null): array
    {
        $byMember = [];

        foreach ($items ?? $this->cancelled() as $item) {
            $byMember[$item['booking']->uid][] = $item;
        }

        $messages = [];

        foreach ($byMember as $memberItems) {
            $user = $memberItems[0]['booking']->user;
            $text = $this->text($user, $memberItems);
            $phone = $user->phone ?: null;

            $messages[] = [
                'user' => $user,
                'name' => $user->fullName(),
                'phone' => $phone,
                'items' => $memberItems,
                'bookings' => implode(',', array_map(fn (array $item) => $item['booking']->bid, $memberItems)),
                'told' => ! in_array(false, array_column($memberItems, 'told'), true),
                'text' => $text,
                'url' => WhatsApp::to($phone, $text),
            ];
        }

        return $messages;
    }

    /** How many members still have to be told about a cancelled booking (the admin menu's badge): one query. */
    public function memberCount(): int
    {
        return Booking::query()
            ->where('status', 'cancelled')
            ->whereMeta(self::REASON)
            ->whereDoesntHave('metaEntries', fn (Builder $query) => $query->where('key', self::TOLD))
            ->whereHas('reservations', fn (Builder $query) => $this->notStarted($query))
            ->distinct()
            ->count('uid');
    }

    /**
     * Notes that the members of these bookings have been told; bookings a closure didn't cancel are skipped.
     *
     * @param  list<int>  $bookingIds
     */
    public function markTold(array $bookingIds): void
    {
        Booking::query()
            ->whereIn('bid', $bookingIds)
            ->where('status', 'cancelled')
            ->whereMeta(self::REASON)
            ->get()
            ->each(fn (Booking $booking) => $booking->setMeta(self::TOLD, '1'));
    }

    /**
     * "rink A-1 on Sat 3 Oct, 12:00–13:00", as the messages and the Secretary's lists show a booking.
     *
     * @param  Displaced  $item
     */
    public static function describe(array $item): string
    {
        return sprintf(
            'rink %s on %s, %s–%s',
            $item['booking']->rink->name,
            $item['start']->format('D j M'),
            $item['start']->format('H:i'),
            $item['end']->format('H:i'),
        );
    }

    /**
     * The bookings' slots that haven't started yet, live or cancelled.
     *
     * @return list<array{booking: Booking, start: CarbonImmutable, end: CarbonImmutable, told: bool}>
     */
    private function slots(bool $cancelled, ?string $green, ?CarbonInterface $day): array
    {
        $now = CarbonImmutable::now();

        $reservations = Reservation::query()
            ->when(
                $day !== null,
                fn (Builder $query) => $query->where('date', $day?->toDateString()),
                fn (Builder $query) => $query->where('date', '>=', $now->toDateString()),
            )
            ->whereHas('booking', fn (Builder $query) => $cancelled
                ? $query->where('status', 'cancelled')->whereMeta(self::REASON)
                : $query->where('status', '!=', 'cancelled'))
            ->with(['booking.rink', 'booking.user.metaEntries', 'booking.metaEntries'])
            ->orderBy('date')
            ->orderBy('time_start')
            ->get();

        $slots = [];

        foreach ($reservations as $reservation) {
            $booking = $reservation->booking;

            if ($booking->rink === null || $booking->user === null || ($green !== null && $booking->rink->green() !== $green)) {
                continue;
            }

            $date = $reservation->date->format('Y-m-d');
            $start = CarbonImmutable::parse($date.' '.$reservation->time_start);

            if ($start->lessThanOrEqualTo($now)) {
                continue;
            }

            $slots[] = [
                'booking' => $booking,
                'start' => $start,
                'end' => CarbonImmutable::parse($date.' '.$reservation->time_end),
                'told' => $booking->meta(self::TOLD) === '1',
            ];
        }

        return $slots;
    }

    /**
     * Reservations that haven't started: a later day, or later today.
     *
     * @param  Builder<Reservation>  $query
     */
    private function notStarted(Builder $query): void
    {
        $now = CarbonImmutable::now();

        $query->where('date', '>', $now->toDateString())
            ->orWhere(fn (Builder $today) => $today->where('date', $now->toDateString())->where('time_start', '>', $now->format('H:i:s')));
    }

    /**
     * Why the booking can't go ahead, as the end of "...because <reason>". Closures come before events, as
     * on the calendar.
     *
     * @param  Collection<int, Event>  $events
     */
    private function reason(Rink $rink, CarbonImmutable $start, CarbonImmutable $end, Collection $events): ?string
    {
        if ($this->rules->isDayHidden($start)) {
            return 'the club is closed that day';
        }

        if ($rink->status === 'disabled') {
            return 'rink '.$rink->name.' is closed until further notice';
        }

        if ($this->greens->isClosed($rink->green(), $start)) {
            return 'green '.$rink->green().' is closed that day';
        }

        $event = $events->first(fn (Event $event) => $event->covers($rink)
            && $event->datetime_start->lessThan($end) && $event->datetime_end->greaterThan($start));

        return $event !== null ? 'the rink is set aside for '.$event->meta('name', 'an event') : null;
    }

    /**
     * The WhatsApp text: a greeting, the cancelled booking (or a list of them) with the reason, a reminder to
     * tell the playing partners (who may not be members, so get no message of their own) and where to book again.
     *
     * @param  non-empty-list<Displaced>  $items
     */
    private function text(User $user, array $items): string
    {
        $club = $this->settings->clubName();
        $hello = 'Hi '.$user->greetingName().',';
        $partners = array_merge(...array_map(fn (array $item) => $item['booking']->playerNames(), $items));
        $after = ' Sorry for the inconvenience.'
            .($partners === [] ? '' : (count($items) === 1 ? ' Please let '.implode(' and ', $partners).' know.' : ' Please let your playing partners know.'))
            .' You can book another rink at '.route('home');

        if (count($items) === 1) {
            return $hello.' your booking of '.self::describe($items[0]).' at '.$club.' has been cancelled because '
                .$items[0]['reason'].'.'.$after;
        }

        return $hello.' these bookings of yours at '.$club." have been cancelled:\n"
            .implode("\n", array_map(fn (array $item) => '- '.self::describe($item).': '.$item['reason'], $items))."\n"
            .ltrim($after);
    }
}
