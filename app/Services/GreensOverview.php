<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Rink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The greens overview on the home page: for the next 14 days (hidden days left out), per green whether it
 * is closed, how many of its slots are still free out of how many, and the names of the events on it.
 *
 * Ported from greensOverview() in the original app's Frontend\Controller\IndexController. A slot is free
 * when it hasn't started, no event covers it and it has room for another booking. Closing a green doesn't
 * change the numbers; the page shows the green as closed instead.
 */
class GreensOverview
{
    public const DAYS = 14;

    public function __construct(
        private readonly GreenService $greens,
        private readonly BookingRules $rules,
    ) {}

    /**
     * @return list<array{date: CarbonImmutable, closed: array<string, bool>, free: array<string, int>, slots: array<string, int>, events: array<string, list<string>>}>
     */
    public function days(): array
    {
        $greens = $this->greens->greens();
        $from = CarbonImmutable::today();
        $until = $from->addDays(self::DAYS);
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

        for ($day = $from; $day->lessThan($until); $day = $day->addDay()) {
            if ($this->rules->isDayHidden($day)) {
                continue;
            }

            $dayEvents = $events->filter(fn (Event $event) => $event->datetime_start->lessThan($day->addDay())
                && $event->datetime_end->greaterThan($day));

            $entry = ['date' => $day, 'closed' => [], 'free' => [], 'slots' => [], 'events' => []];

            foreach ($greens as $green => $rinks) {
                $entry['closed'][$green] = $this->greens->isClosed($green, $day);
                $entry['free'][$green] = 0;
                $entry['slots'][$green] = 0;
                $entry['events'][$green] = $this->eventNames($dayEvents, $rinks);

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
