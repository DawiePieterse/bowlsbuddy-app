<?php

namespace App\Services;

use App\Models\Rink;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Greens: a rink's green is the part of its name before the dash ("A-1" is on green "A").
 * The Secretary closes a green per day through the "service.greens.closed" setting, one
 * "YYYY-MM-DD:G" entry per line. Hidden days come from "service.calendar.day-exceptions":
 * weekday names or "Y-m-d" dates, one per line or comma; a "+Y-m-d" entry re-allows a date
 * whose weekday is hidden.
 */
class GreenService
{
    public function __construct(private Settings $settings) {}

    /**
     * The greens, in rink priority order.
     *
     * @return list<string>
     */
    public function greens(): array
    {
        $greens = [];

        foreach (Rink::query()->visible()->orderBy('priority')->pluck('name') as $name) {
            $green = trim(explode('-', $name, 2)[0]);

            if ($green !== '' && ! in_array($green, $greens, true)) {
                $greens[] = $green;
            }
        }

        return $greens;
    }

    public function isClosed(string $green, CarbonInterface $date): bool
    {
        return in_array($green, $this->closedGreens($date), true);
    }

    /**
     * The greens closed on this day.
     *
     * @return list<string>
     */
    public function closedGreens(CarbonInterface $date): array
    {
        $day = $date->format('Y-m-d');
        $closed = [];

        foreach ($this->closedEntries() as [$entryDay, $green]) {
            if ($entryDay === $day && ! in_array($green, $closed, true)) {
                $closed[] = $green;
            }
        }

        return $closed;
    }

    public function close(string $green, CarbonInterface $date): void
    {
        if (! $this->isClosed($green, $date)) {
            $entries = $this->closedEntries();
            $entries[] = [$date->format('Y-m-d'), $green];

            $this->storeClosedEntries($entries);
        }
    }

    public function open(string $green, CarbonInterface $date): void
    {
        $day = $date->format('Y-m-d');

        $entries = array_values(array_filter(
            $this->closedEntries(),
            fn (array $entry) => $entry !== [$day, $green],
        ));

        $this->storeClosedEntries($entries);
    }

    /**
     * Whether this day is hidden from the calendar (rule 6, old SquareValidator day exceptions).
     */
    public function isHiddenDay(CarbonInterface $date): bool
    {
        $exceptions = (string) $this->settings->get('service.calendar.day-exceptions', '');

        if (trim($exceptions) === '') {
            return false;
        }

        $day = $date->format('Y-m-d');
        $weekday = $date->format('l');
        $hidden = false;
        $allowed = false;

        foreach (preg_split('/[\n,]/', $exceptions) ?: [] as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if ($entry[0] === '+') {
                $allowed = $allowed || trim($entry, '+ ') === $day;
            } else {
                $hidden = $hidden || $entry === $day || strcasecmp($entry, $weekday) === 0;
            }
        }

        return $hidden && ! $allowed;
    }

    /**
     * The next $count playing days (days not hidden from the calendar), starting today.
     *
     * @return list<CarbonImmutable>
     */
    public function playingDays(int $count = 14, ?CarbonInterface $from = null): array
    {
        $day = CarbonImmutable::instance($from ?? now())->startOfDay();
        $days = [];

        // A guard far past any sane exception list, so a "hide everything" setting cannot loop forever.
        for ($i = 0; $i < 366 && count($days) < $count; $i++) {
            if (! $this->isHiddenDay($day)) {
                $days[] = $day;
            }

            $day = $day->addDay();
        }

        return $days;
    }

    /** @return list<array{string, string}> [date "Y-m-d", green] */
    private function closedEntries(): array
    {
        $entries = [];

        $raw = (string) $this->settings->get('service.greens.closed', '');

        foreach (preg_split('/[\n,]/', $raw) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^(\d{4}-\d{2}-\d{2}):(.+)$/', $line, $parts)) {
                $entries[] = [$parts[1], trim($parts[2])];
            }
        }

        return $entries;
    }

    /** @param list<array{string, string}> $entries */
    private function storeClosedEntries(array $entries): void
    {
        sort($entries);

        $lines = array_map(fn (array $entry) => $entry[0].':'.$entry[1], $entries);

        $this->settings->set('service.greens.closed', implode("\n", $lines));
    }
}
