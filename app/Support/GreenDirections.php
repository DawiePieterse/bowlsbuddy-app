<?php

namespace App\Support;

use App\Models\GreenDirection;
use App\Models\Rink;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Direction of play per green and day. A direction set for a day holds on the days after it until the Club
 * Secretary sets another, so it only needs setting when it changes.
 */
class GreenDirections
{
    /** @return list<string> the club's greens, in rink order */
    public function greens(): array
    {
        return Rink::query()->visible()->orderBy('priority')->get()
            ->map(fn (Rink $rink) => $rink->green())->unique()->values()->all();
    }

    /** @return array<string, string|null> green => direction ("north-south", "east-west", or null when never set) */
    public function forDay(Carbon|string $date): array
    {
        $date = Carbon::parse($date);

        return $this->forDays($date, $date)[$date->toDateString()];
    }

    /**
     * Two queries whatever the number of days.
     *
     * @return array<string, array<string, string|null>> date => green => direction, from $from to $to
     */
    public function forDays(Carbon|string $from, Carbon|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        $greens = $this->greens();

        // Every change up to the last day, oldest first; the latest one on or before a day applies to it.
        $changes = GreenDirection::query()
            ->whereIn('green', $greens)
            ->where('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->get();

        $current = array_fill_keys($greens, null);
        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            while ($changes->isNotEmpty() && $changes->first()->date->lte($day)) {
                $change = $changes->shift();
                $current[$change->green] = $change->direction;
            }

            $days[$day->toDateString()] = $current;
        }

        return $days;
    }

    public function set(string $green, Carbon|string $date, string $direction): void
    {
        if (! array_key_exists($direction, GreenDirection::DIRECTIONS)) {
            throw new InvalidArgumentException("Unknown direction of play: $direction");
        }

        GreenDirection::query()->updateOrCreate(
            ['green' => $green, 'date' => Carbon::parse($date)->toDateString()],
            ['direction' => $direction],
        );
    }

    public static function label(?string $direction): string
    {
        return GreenDirection::DIRECTIONS[$direction] ?? 'Not set';
    }
}
