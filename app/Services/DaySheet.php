<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Rink;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The Secretary's printable day sheet: per green whether it is closed, its time slots, and per rink and slot
 * the bookings (with their members and player names) and the names of the events on it.
 *
 * Ported from daySheetAction() in the original app's Frontend\Controller\IndexController.
 */
class DaySheet
{
    public function __construct(private readonly GreenService $greens) {}

    /**
     * @return array<string, array{closed: bool, slots: list<array{int, int}>, rinks: list<array{rink: Rink, cells: list<array{bookings: list<Booking>, events: list<string>}>}>}>
     */
    public function for(CarbonInterface $date): array
    {
        $dayStart = CarbonImmutable::instance($date)->startOfDay();
        $dayEnd = $dayStart->addDay();

        /** @var array<int, list<array{int, int, Booking|null, string|null}>> $entries sid => [start, end, booking, event name] */
        $entries = [];

        $reservations = Reservation::query()
            ->with(['booking.user.metaEntries', 'booking.metaEntries'])
            ->where('date', $dayStart->toDateString())
            ->orderBy('time_start')
            ->get();

        foreach ($reservations as $reservation) {
            $booking = $reservation->booking;

            if ($booking->status !== 'cancelled') {
                $entries[$booking->sid][] = [
                    BookingRules::seconds($reservation->time_start),
                    BookingRules::seconds($reservation->time_end),
                    $booking,
                    null,
                ];
            }
        }

        $events = Event::query()
            ->with('metaEntries')
            ->where('status', 'enabled')
            ->where('datetime_end', '>', $dayStart)
            ->where('datetime_start', '<', $dayEnd)
            ->orderBy('datetime_start')
            ->get();

        $greens = $this->greens->greens();

        foreach ($events as $event) {
            $start = (int) $dayStart->diffInSeconds($event->datetime_start->max($dayStart));
            $end = (int) $dayStart->diffInSeconds($event->datetime_end->min($dayEnd));

            foreach ($greens as $rinks) {
                foreach ($rinks as $rink) {
                    if ($event->covers($rink)) {
                        $entries[$rink->sid][] = [$start, $end, null, (string) $event->meta('name')];
                    }
                }
            }
        }

        $sheet = [];

        foreach ($greens as $green => $rinks) {
            $slots = [];

            foreach ($rinks as $rink) {
                $block = max(60, $rink->time_block);
                $closes = BookingRules::seconds($rink->time_end);

                for ($slotStart = BookingRules::seconds($rink->time_start); $slotStart < $closes; $slotStart += $block) {
                    $slots[$slotStart] = [$slotStart, min($slotStart + $block, $closes)];
                }
            }

            ksort($slots);
            $slots = array_values($slots);

            $rows = [];

            foreach ($rinks as $rink) {
                $cells = [];

                foreach ($slots as [$slotStart, $slotEnd]) {
                    $cell = ['bookings' => [], 'events' => []];

                    foreach ($entries[$rink->sid] ?? [] as [$start, $end, $booking, $eventName]) {
                        if ($start < $slotEnd && $end > $slotStart) {
                            if ($booking !== null) {
                                $cell['bookings'][] = $booking;
                            } elseif (! in_array($eventName, $cell['events'], true)) {
                                $cell['events'][] = (string) $eventName;
                            }
                        }
                    }

                    $cells[] = $cell;
                }

                $rows[] = ['rink' => $rink, 'cells' => $cells];
            }

            $sheet[$green] = [
                'closed' => $this->greens->isClosed($green, $dayStart),
                'slots' => $slots,
                'rinks' => $rows,
            ];
        }

        return $sheet;
    }
}
