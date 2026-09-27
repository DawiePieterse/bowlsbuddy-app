<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * How much each member used the rinks in a period: hours and bookings they made, counted by the member
 * who booked (partners' names are free text, so they can't be counted). Cancelled bookings don't count.
 * Feeds the "Use of rinks" tab on the Members page.
 */
class MemberUsage
{
    /**
     * Limits the members to those who booked in the period and adds "usage_hours" and "usage_bookings".
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function applyTo(Builder $query, CarbonInterface $from, CarbonInterface $until): Builder
    {
        $reservations = fn (): QueryBuilder => $this->reservations($from, $until)
            ->whereColumn('b.uid', 'bs_users.uid');

        return $query
            ->whereExists($reservations())
            ->addSelect([
                'usage_hours' => $reservations()->selectRaw('SUM(TIME_TO_SEC(TIMEDIFF(r.time_end, r.time_start))) / 3600'),
                'usage_bookings' => $reservations()->selectRaw('COUNT(DISTINCT b.bid)'),
            ]);
    }

    /** @return array{hours: float, bookings: int, members: int} everyone's use together */
    public function totals(CarbonInterface $from, CarbonInterface $until): array
    {
        $row = $this->reservations($from, $until)
            ->selectRaw('SUM(TIME_TO_SEC(TIMEDIFF(r.time_end, r.time_start))) / 3600 AS hours')
            ->selectRaw('COUNT(DISTINCT b.bid) AS bookings')
            ->selectRaw('COUNT(DISTINCT b.uid) AS members')
            ->first();

        return [
            'hours' => round((float) ($row->hours ?? 0), 2),
            'bookings' => (int) ($row->bookings ?? 0),
            'members' => (int) ($row->members ?? 0),
        ];
    }

    private function reservations(CarbonInterface $from, CarbonInterface $until): QueryBuilder
    {
        return DB::table('bs_reservations as r')
            ->join('bs_bookings as b', 'b.bid', '=', 'r.bid')
            ->where('b.status', '!=', 'cancelled')
            ->whereBetween('r.date', [$from->toDateString(), $until->toDateString()]);
    }
}
