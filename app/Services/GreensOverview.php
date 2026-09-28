<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Rink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The greens overview on the home page: for the next 14 playing days, per green whether it is closed, how
 * many of its slots are still free out of how many, and the names of the events on it.
 *
 * Ported from greensOverview() in the original app's Frontend\Controller\IndexController, which took the
 * next 14 calendar days and left the hidden ones out. A club that plays three days a week then saw only six
 * days, so this counts 14 playing days instead. A slot is free when it hasn't started, is within the rink's
 * booking range, no event covers it and it has room for another booking. Closing a green doesn't change the
 * numbers; the page shows the green as closed instead.
 */
class GreensOverview
{
    public const DAYS = 14;

    /** How far ahead to look for playing days, so a club with every day hidden still gets an answer. */
    private const SEARCH_DAYS = 366;

    public function __construct(
        private readonly GreenService $greens,
        private readonly BookingRules $rules,
    ) {}

    /**
     * The next 14 days that aren't hidden from the calendar, starting today.
     *
     * @return list<CarbonImmutable>
     */
    public function playingDays(): array
    {
        $days = [];
        $today = CarbonImmutable::today();

        for ($day = $today; count($days) < self::DAYS && $day->lessThan($today->addDays(self::SEARCH_DAYS)); $day = $day->addDay()) {
            if (! $this->rules->isDayHidden($day)) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Per green, "opens" is when members can start booking that day (the rinks' booking range), or null
     * when they already can.
     *
     * @return list<array{date: CarbonImmutable, closed: array<string, bool>, free: array<string, int>, slots: array<string, int>, events: array<string, list<string>>, opens: array<string, CarbonImmutable|null>}>
     */
    public function days(): array
    {
        $playingDays = $this->playingDays();

        if ($playingDays === []) {
            return [];
        }

        $greens = $this->greens->greens();
        $from = $playingDays[0];
        $until = end($playingDays)->addDay();
        $now = CarbonImmutable::now();

        $booked = $this->bookedTimes($from, $until);
        $events = Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_end', '>', $from)
            ->where('datetime_start', '<', $until)
            ->orderBy('datetime_start')
            ->orderBy('eid')
            ->get();

        $days = [];

        foreach ($playingDays as $day) {
            $dayEvents = $events->filter(fn (Event $event) => $event->datetime_start->lessThan($day->addDay())
                && $event->datetime_end->greaterThan($day));

            $entry = ['date' => $day, 'closed' => [], 'free' => [], 'slots' => [], 'events' => [], 'opens' => []];

            foreach ($greens as $green => $rinks) {
                $entry['closed'][$green] = $this->greens->isClosed($green, $day);
                $entry['free'][$green] = 0;
                $entry['slots'][$green] = 0;
                $entry['events'][$green] = $this->eventNames($dayEvents, $rinks);
                $entry['opens'][$green] = $this->opens($rinks, $day, $now);

                foreach ($rinks as $rink) {
                    [$free, $total] = $this->countSlots($rink, $day, $booked[$day->toDateString()][$rink->sid] ?? [], $dayEvents, $now);

                    $entry['free'][$green] += $free;
                    $entry['slots'][$green] += $total;
                }
            }

            $days[] = $entry;
        }

        return $days;
    }

    /**
     * Active public bookings as date => sid => list of [start second, end second, players].
     *
     * @return array<string, array<int, list<array{int, int, int}>>>
     */
    private function bookedTimes(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $rows = DB::table('bs_reservations')
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.status', '!=', 'cancelled')
            ->where('bs_bookings.visibility', 'public')
            ->where('bs_reservations.date', '>=', $from->toDateString())
            ->where('bs_reservations.date', '<', $until->toDateString())
            ->get(['bs_reservations.date', 'bs_reservations.time_start', 'bs_reservations.time_end', 'bs_bookings.sid', 'bs_bookings.quantity']);

        $booked = [];

        foreach ($rows as $row) {
            $booked[substr((string) $row->date, 0, 10)][(int) $row->sid][] = [
                BookingRules::seconds((string) $row->time_start),
                BookingRules::seconds((string) $row->time_end),
                (int) $row->quantity,
            ];
        }

        return $booked;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @param  Collection<int, Rink>  $rinks
     * @return list<string>
     */
    private function eventNames(Collection $events, Collection $rinks): array
    {
        return $events
            ->filter(fn (Event $event) => $rinks->contains(fn (Rink $rink) => $event->covers($rink)))
            ->map(fn (Event $event) => (string) $event->meta('name'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The moment the first rink of the green can be booked for the day, or null when it already can.
     *
     * @param  Collection<int, Rink>  $rinks
     */
    private function opens(Collection $rinks, CarbonImmutable $day, CarbonImmutable $now): ?CarbonImmutable
    {
        $opens = null;

        foreach ($rinks as $rink) {
            if (! $rink->range_book) {
                return null;
            }

            $first = $day->addSeconds(BookingRules::seconds($rink->time_start))->subSeconds($rink->range_book);

            if ($first->lessThanOrEqualTo($now)) {
                return null;
            }

            $opens = $opens === null || $first->lessThan($opens) ? $first : $opens;
        }

        return $opens;
    }

    /**
     * @param  list<array{int, int, int}>  $booked
     * @param  Collection<int, Event>  $events
     * @return array{int, int} free and total slots of the rink that day
     */
    private function countSlots(Rink $rink, CarbonImmutable $day, array $booked, Collection $events, CarbonImmutable $now): array
    {
        $block = max(60, $rink->time_block);
        $closes = BookingRules::seconds($rink->time_end);
        $free = 0;
        $total = 0;

        for ($slotStart = BookingRules::seconds($rink->time_start); $slotStart < $closes; $slotStart += $block) {
            $slotEnd = $slotStart + $block;
            $total++;

            if ($day->addSeconds($slotStart)->lessThanOrEqualTo($now)) {
                continue;
            }

            if ($rink->range_book && $day->addSeconds($slotStart)->greaterThan($now->addSeconds($rink->range_book))) {
                continue;
            }

            $taken = $events->contains(fn (Event $event) => $event->datetime_start->lessThan($day->addSeconds($slotEnd))
                && $event->datetime_end->greaterThan($day->addSeconds($slotStart))
                && $event->covers($rink));

            if ($taken) {
                continue;
            }

            $players = 0;

            foreach ($booked as [$bookedStart, $bookedEnd, $quantity]) {
                if ($bookedStart < $slotEnd && $bookedEnd > $slotStart) {
                    $players += $quantity;
                }
            }

            if ($players < $rink->capacity && ! ($players > 0 && ! $rink->capacity_heterogenic)) {
                $free++;
            }
        }

        return [$free, $total];
    }
}
