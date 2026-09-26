<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\Rink;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The greens overview (PLAN.md 5.3 rule 8): the next 14 playing days with, per green, the free
 * and total slots, whether the green is closed and the names of its events. Free means bookable
 * by a member right now, so closed greens, events, occupied slots and today's finished slots
 * don't count. Everything is loaded in three queries, whatever the number of days.
 */
class GreensOverview
{
    public function __construct(private GreenService $greens) {}

    /**
     * @return list<array{date: CarbonImmutable, greens: array<string, array{total: int, free: int, closed: bool, events: list<string>}>}>
     */
    public function days(int $count = 14, ?CarbonInterface $from = null): array
    {
        $days = $this->greens->playingDays($count, $from);

        if (! $days) {
            return [];
        }

        $rinks = Rink::query()->visible()->orderBy('priority')->get();
        $byGreen = $rinks->groupBy(fn (Rink $rink) => $rink->green());

        $first = $days[0];
        $last = end($days);

        /** @var array<int, array<string, list<array{string, string, int}>>> sid => date => [start, end, players] */
        $booked = [];

        $reservations = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.visibility', 'public')
            ->where('bs_bookings.status', '!=', 'cancelled')
            ->whereBetween('date', [$first->format('Y-m-d'), $last->format('Y-m-d')])
            ->toBase()
            ->get(['bs_reservations.date', 'bs_reservations.time_start', 'bs_reservations.time_end', 'bs_bookings.sid', 'bs_bookings.quantity']);

        foreach ($reservations as $reservation) {
            $booked[(int) $reservation->sid][substr((string) $reservation->date, 0, 10)][] = [
                (string) $reservation->time_start, (string) $reservation->time_end, (int) $reservation->quantity,
            ];
        }

        $events = Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_start', '<', $last->addDay())
            ->where('datetime_end', '>', $first)
            ->orderBy('datetime_start')
            ->get();

        $overview = [];

        foreach ($days as $day) {
            $greens = [];

            foreach ($byGreen as $green => $greenRinks) {
                $closed = $this->greens->isClosed($green, $day);
                $total = 0;
                $free = 0;
                $names = [];

                foreach ($greenRinks as $rink) {
                    $dayEvents = $events->filter(
                        fn (Event $event) => $event->covers($rink)
                            && $event->datetime_start < $day->setTimeFromTimeString($rink->time_end)
                            && $event->datetime_end > $day->setTimeFromTimeString($rink->time_start)
                    );

                    foreach ($dayEvents as $event) {
                        $name = trim((string) $event->meta('name'));

                        if ($name !== '' && ! in_array($name, $names, true)) {
                            $names[] = $name;
                        }
                    }

                    foreach ($this->slots($rink, $day) as [$start, $end]) {
                        $total++;

                        if ($closed || $this->finished($rink, $start)) {
                            continue;
                        }

                        if ($dayEvents->first(fn (Event $event) => $event->datetime_start < $end && $event->datetime_end > $start)) {
                            continue;
                        }

                        if ($this->slotFull($rink, $booked[$rink->sid][$day->format('Y-m-d')] ?? [], $start, $end)) {
                            continue;
                        }

                        $free++;
                    }
                }

                $greens[$green] = ['total' => $total, 'free' => $free, 'closed' => $closed, 'events' => $names];
            }

            $overview[] = ['date' => $day, 'greens' => $greens];
        }

        return $overview;
    }

    /**
     * The rink's slots on this day.
     *
     * @return list<array{CarbonImmutable, CarbonImmutable}>
     */
    public function slots(Rink $rink, CarbonInterface $day): array
    {
        $day = CarbonImmutable::instance($day);
        $start = $day->setTimeFromTimeString($rink->time_start);
        $dayEnd = $day->setTimeFromTimeString($rink->time_end);
        $slots = [];

        while ($rink->time_block > 0 && $start->addSeconds($rink->time_block) <= $dayEnd) {
            $slots[] = [$start, $start->addSeconds($rink->time_block)];
            $start = $start->addSeconds($rink->time_block);
        }

        return $slots;
    }

    /** A slot no longer bookable today (it stays bookable through its first half, as in the rules). */
    private function finished(Rink $rink, CarbonImmutable $start): bool
    {
        return $start < now()->subSeconds((int) ($rink->time_block_bookable / 2));
    }

    /** @param list<array{string, string, int}> $dayBookings [start, end, players] */
    private function slotFull(Rink $rink, array $dayBookings, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $players = 0;

        foreach ($dayBookings as [$bookedStart, $bookedEnd, $quantity]) {
            if ($bookedStart < $end->format('H:i:s') && $bookedEnd > $start->format('H:i:s')) {
                $players += $quantity;
            }
        }

        if ($players === 0) {
            return false;
        }

        return ! $rink->capacity_heterogenic || $players >= $rink->capacity;
    }
}
