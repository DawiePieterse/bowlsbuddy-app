<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Rink;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The Secretary's utilisation heatmap: booked hours per rink over the last week, month, quarter or year,
 * split by the direction the green was played in (North-South, East-West, or not indicated) so wear can
 * be compared both ways. Cancelled bookings don't count; events (blocked time) are not bookings.
 */
class RinkUtilisation
{
    public const PERIODS = [
        'week' => 'Last week',
        'month' => 'Last month',
        'quarter' => 'Last quarter',
        'year' => 'Last year',
    ];

    /** Direction keys in display order; '' is a day without an indicated direction. */
    public const DIRECTIONS = ['NS', 'EW', ''];

    public function __construct(private readonly GreenService $greens) {}

    /**
     * The first and last day of a period, both included: the last 7 days, or a month, quarter or year
     * back from today.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function range(string $period, ?CarbonInterface $today = null): array
    {
        if (! array_key_exists($period, self::PERIODS)) {
            throw new \InvalidArgumentException('The period is week, month, quarter or year.');
        }

        $until = CarbonImmutable::instance($today ?? CarbonImmutable::today())->startOfDay();

        return [match ($period) {
            'week' => $until->subDays(6),
            'month' => $until->subMonthNoOverflow()->addDay(),
            'quarter' => $until->subMonthsNoOverflow(3)->addDay(),
            default => $until->subYearNoOverflow()->addDay(),
        }, $until];
    }

    public static function directionLabel(string $direction): string
    {
        return GreenService::DIRECTIONS[$direction] ?? 'Direction not indicated';
    }

    /**
     * @return array{
     *     period: string,
     *     from: CarbonImmutable,
     *     until: CarbonImmutable,
     *     columns: list<array{key: string, label: string}>,
     *     greens: array<string, array{
     *         max: float,
     *         directions: array<string, array{days: int, total: float, rinks: list<array{rink: Rink, hours: array<string, float>, total: float}>}>
     *     }>
     * }
     */
    public function for(string $period, ?CarbonInterface $today = null): array
    {
        [$from, $until] = self::range($period, $today);

        $columns = $this->columns($period, $from, $until);
        $directions = $this->greens->directions();
        $greens = $this->greens->greens();
        $rinkGreen = [];

        foreach ($greens as $green => $rinks) {
            foreach ($rinks as $rink) {
                $rinkGreen[$rink->sid] = $green;
            }
        }

        /** @var array<int, array<string, array<string, float>>> $hours sid => direction => column => hours */
        $hours = [];
        /** @var array<string, array<string, array<string, true>>> $playedDays green => direction => date => true */
        $playedDays = [];

        $reservations = Reservation::query()
            ->join('bs_bookings', 'bs_bookings.bid', '=', 'bs_reservations.bid')
            ->where('bs_bookings.status', '!=', 'cancelled')
            ->whereIn('bs_bookings.sid', array_keys($rinkGreen))
            ->whereBetween('bs_reservations.date', [$from->toDateString(), $until->toDateString()])
            ->get(['bs_bookings.sid', 'bs_reservations.date', 'bs_reservations.time_start', 'bs_reservations.time_end']);

        foreach ($reservations as $reservation) {
            /** @var int $sid */
            $sid = $reservation->getAttribute('sid');
            $date = CarbonImmutable::instance($reservation->date);
            $green = $rinkGreen[$sid];
            $direction = $directions[$date->toDateString().':'.$green] ?? '';
            $column = $this->columnKey($period, $date);
            $duration = max(0, BookingRules::seconds($reservation->time_end) - BookingRules::seconds($reservation->time_start)) / 3600;

            $hours[$sid][$direction][$column] = ($hours[$sid][$direction][$column] ?? 0) + $duration;
            $playedDays[$green][$direction][$date->toDateString()] = true;
        }

        $result = [];

        foreach ($greens as $green => $rinks) {
            $result[$green] = $this->green($rinks, $columns, $hours, $playedDays[$green] ?? []);
        }

        return [
            'period' => $period,
            'from' => $from,
            'until' => $until,
            'columns' => $columns,
            'greens' => $result,
        ];
    }

    /**
     * @param  Collection<int, Rink>  $rinks
     * @param  list<array{key: string, label: string}>  $columns
     * @param  array<int, array<string, array<string, float>>>  $hours
     * @param  array<string, array<string, true>>  $playedDays
     * @return array{max: float, directions: array<string, array{days: int, total: float, rinks: list<array{rink: Rink, hours: array<string, float>, total: float}>}>}
     */
    private function green(Collection $rinks, array $columns, array $hours, array $playedDays): array
    {
        $max = 0.0;
        $directions = [];

        foreach (self::DIRECTIONS as $direction) {
            $rows = [];
            $total = 0.0;

            foreach ($rinks as $rink) {
                $cells = [];
                $rinkTotal = 0.0;

                foreach ($columns as $column) {
                    $value = round($hours[$rink->sid][$direction][$column['key']] ?? 0, 2);
                    $cells[$column['key']] = $value;
                    $rinkTotal += $value;
                    $max = max($max, $value);
                }

                $rows[] = ['rink' => $rink, 'hours' => $cells, 'total' => round($rinkTotal, 2)];
                $total += $rinkTotal;
            }

            $directions[$direction] = [
                'days' => count($playedDays[$direction] ?? []),
                'total' => round($total, 2),
                'rinks' => $rows,
            ];
        }

        return ['max' => $max, 'directions' => $directions];
    }

    /**
     * The heatmap's columns: days for a week or month, weeks for a quarter, months for a year.
     *
     * @return list<array{key: string, label: string}>
     */
    private function columns(string $period, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $columns = [];

        for ($day = $from; $day <= $until; $day = $day->addDay()) {
            $key = $this->columnKey($period, $day);

            if (isset($columns[$key])) {
                continue;
            }

            $columns[$key] = ['key' => $key, 'label' => match ($period) {
                'week' => $day->format('D j'),
                'month' => $day->format('j'),
                'quarter' => CarbonImmutable::parse($key)->format('j M'),
                default => $day->format('M'),
            }];
        }

        return array_values($columns);
    }

    private function columnKey(string $period, CarbonImmutable $day): string
    {
        return match ($period) {
            'week', 'month' => $day->toDateString(),
            'quarter' => $day->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            default => $day->format('Y-m'),
        };
    }
}
