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
use Illuminate\Support\Collection;

/**
 * Upcoming bookings that can't go ahead: the club is closed that day, the rink was taken out of use (a hidden
 * green), its green was closed for the day, or an event took the rink. Nothing is cancelled, because closing a
 * green can be undone and the bookings come back with it. Instead the Secretary gets one WhatsApp message per
 * member to send from their own phone; the app itself sends nothing.
 *
 * @phpstan-type Displaced array{booking: Booking, start: CarbonImmutable, end: CarbonImmutable, reason: string, event: Event|null}
 * @phpstan-type Message array{user: User, name: string, phone: string|null, items: list<Displaced>, text: string, url: string|null}
 */
class DisplacedBookings
{
    public function __construct(
        private readonly GreenService $greens,
        private readonly BookingRules $rules,
        private readonly Settings $settings,
    ) {}

    /**
     * Affected bookings that haven't started yet, soonest first; only those on one green and one day when given.
     *
     * @return list<Displaced>
     */
    public function upcoming(?string $green = null, ?CarbonInterface $day = null): array
    {
        $now = CarbonImmutable::now();

        $reservations = Reservation::query()
            ->when(
                $day !== null,
                fn (Builder $query) => $query->where('date', $day?->toDateString()),
                fn (Builder $query) => $query->where('date', '>=', $now->toDateString()),
            )
            ->whereHas('booking', fn (Builder $query) => $query->where('status', '!=', 'cancelled'))
            ->with(['booking.rink', 'booking.user.metaEntries', 'booking.metaEntries'])
            ->orderBy('date')
            ->orderBy('time_start')
            ->get();

        $events = Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_end', '>', $now)
            ->orderBy('datetime_start')
            ->get();

        $displaced = [];

        foreach ($reservations as $reservation) {
            $booking = $reservation->booking;
            $rink = $booking->rink;

            if ($rink === null || $booking->user === null || ($green !== null && $rink->green() !== $green)) {
                continue;
            }

            $date = $reservation->date->format('Y-m-d');
            $start = CarbonImmutable::parse($date.' '.$reservation->time_start);
            $end = CarbonImmutable::parse($date.' '.$reservation->time_end);

            if ($start->lessThanOrEqualTo($now)) {
                continue;
            }

            $why = $this->reason($rink, $start, $end, $events);

            if ($why !== null) {
                $displaced[] = ['booking' => $booking, 'start' => $start, 'end' => $end, 'reason' => $why[0], 'event' => $why[1]];
            }
        }

        return $displaced;
    }

    /**
     * The upcoming bookings an event takes over (and that nothing else had already displaced).
     *
     * @return list<Displaced>
     */
    public function forEvent(Event $event): array
    {
        return array_values(array_filter(
            $this->upcoming(),
            fn (array $item) => $item['event']?->eid === $event->eid,
        ));
    }

    /**
     * One message per member, listing all their affected bookings, in the order of their first one.
     *
     * @param  list<Displaced>|null  $displaced  defaults to every upcoming affected booking
     * @return list<Message>
     */
    public function messages(?array $displaced = null): array
    {
        $byMember = [];

        foreach ($displaced ?? $this->upcoming() as $item) {
            $byMember[$item['booking']->uid][] = $item;
        }

        $messages = [];

        foreach ($byMember as $items) {
            $user = $items[0]['booking']->user;
            $text = $this->text($user, $items);
            $phone = $user->phone ?: null;

            $messages[] = [
                'user' => $user,
                'name' => self::nameOf($user),
                'phone' => $phone,
                'items' => $items,
                'text' => $text,
                // wa.me wants the number in international format without the plus.
                'url' => $phone !== null ? 'https://wa.me/'.ltrim($phone, '+').'?text='.rawurlencode($text) : null,
            ];
        }

        return $messages;
    }

    /** How many members have affected upcoming bookings (the admin menu's badge). */
    public function memberCount(): int
    {
        return count(array_unique(array_map(fn (array $item) => $item['booking']->uid, $this->upcoming())));
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

    public static function nameOf(User $user): string
    {
        return trim($user->firstName().' '.$user->lastName()) ?: $user->alias;
    }

    /**
     * Why the booking can't go ahead, as the end of "...can't go ahead: <reason>", and the event behind it.
     * Closures come before events, as on the calendar.
     *
     * @param  Collection<int, Event>  $events
     * @return array{string, Event|null}|null
     */
    private function reason(Rink $rink, CarbonImmutable $start, CarbonImmutable $end, Collection $events): ?array
    {
        if ($this->rules->isDayHidden($start)) {
            return ['the club is closed that day', null];
        }

        if ($rink->status === 'disabled') {
            return ['rink '.$rink->name.' is closed until further notice', null];
        }

        if ($this->greens->isClosed($rink->green(), $start)) {
            return ['green '.$rink->green().' is closed that day', null];
        }

        $event = $events->first(fn (Event $event) => $event->covers($rink)
            && $event->datetime_start->lessThan($end) && $event->datetime_end->greaterThan($start));

        return $event !== null ? ['the rink is set aside for '.$event->meta('name', 'an event'), $event] : null;
    }

    /**
     * The WhatsApp text: a greeting, the booking (or a list of them) with the reason, and a reminder to tell
     * the playing partners, who may not be members and so get no message of their own.
     *
     * @param  non-empty-list<Displaced>  $items
     */
    private function text(User $user, array $items): string
    {
        $club = (string) $this->settings->get('client.name.short', $this->settings->get('client.name.full', 'the club'));
        $hello = 'Hi '.(trim($user->firstName()) ?: $user->alias).',';
        $partners = array_merge(...array_map(fn (array $item) => $item['booking']->playerNames(), $items));

        if (count($items) === 1) {
            return $hello.' your booking of '.self::describe($items[0]).' at '.$club." can't go ahead: "
                .$items[0]['reason'].'.'
                .($partners !== [] ? ' Please let '.implode(' and ', $partners).' know.' : '')
                .' Sorry for the inconvenience.';
        }

        return $hello.' these bookings of yours at '.$club." can't go ahead:\n"
            .implode("\n", array_map(fn (array $item) => '- '.self::describe($item).': '.$item['reason'], $items))."\n"
            .($partners !== [] ? 'Please let your playing partners know. ' : '')
            .'Sorry for the inconvenience.';
    }
}
