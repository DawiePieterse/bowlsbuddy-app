<?php

namespace App\Services;

use App\Models\Rink;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Greens and the days they are closed. A rink belongs to the green named by the prefix of its name ("A-1" is
 * on green "A"). Closed days are kept in the service.greens.closed setting as "YYYY-MM-DD:A" lines.
 *
 * Ported from Square\Manager\GreenManager in the original app.
 */
class GreenService
{
    public const CLOSED_OPTION = 'service.greens.closed';

    public function __construct(private readonly Settings $settings) {}

    /**
     * Visible rinks by green, greens and rinks in natural order ("A-2" before "A-10").
     *
     * @return array<string, Collection<int, Rink>>
     */
    public function greens(): array
    {
        $greens = Rink::query()->visible()->get()
            ->sort(fn (Rink $a, Rink $b) => strnatcmp($a->name, $b->name))
            ->groupBy(fn (Rink $rink) => $rink->green())
            ->map(fn (Collection $rinks) => $rinks->values())
            ->all();

        uksort($greens, 'strnatcmp');

        return $greens;
    }

    public function isClosed(string $green, CarbonInterface $date): bool
    {
        return in_array($date->toDateString().':'.$green, $this->closedEntries(), true);
    }

    public function isRinkClosed(Rink $rink, CarbonInterface $date): bool
    {
        return $this->isClosed($rink->green(), $date);
    }

    /**
     * @param  list<string>  $greens
     * @return array<string, bool> green => whether it is closed that day
     */
    public function closedOn(array $greens, CarbonInterface $date): array
    {
        $closed = [];

        foreach ($greens as $green) {
            $closed[$green] = $this->isClosed($green, $date);
        }

        return $closed;
    }

    /** Opens or closes a green for a day. Entries for days before today are dropped at the same time. */
    public function setClosed(string $green, CarbonInterface $date, bool $closed): void
    {
        $entry = $date->toDateString().':'.$green;
        $today = Carbon::today()->toDateString();

        $entries = array_filter(
            $this->closedEntries(),
            fn (string $existing) => $existing !== $entry && substr($existing, 0, 10) >= $today,
        );

        if ($closed) {
            $entries[] = $entry;
        }

        sort($entries);

        $this->settings->set(self::CLOSED_OPTION, implode("\n", $entries));
    }

    /** @return list<string> */
    private function closedEntries(): array
    {
        $lines = preg_split('~\R~', (string) $this->settings->get(self::CLOSED_OPTION, '')) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
    }
}
