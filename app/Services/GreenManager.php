<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Support\Licensing\Modules;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The Secretary's green management: add, rename, hide or delete a whole green. A green is the
 * prefix of its rinks' names ("A-1" is on green "A"), so these are bulk operations on rinks,
 * plus the loose ends that name a green: green events (bs_events_meta "green") and closed days
 * (the service.greens.closed setting). Some clubs have one green, others three.
 */
class GreenManager
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Modules $modules,
    ) {}

    /**
     * Every green, hidden rinks included (the member pages use GreenService::greens(), which
     * shows only visible ones).
     *
     * @return array<string, Collection<int, Rink>>
     */
    public function all(): array
    {
        $greens = Rink::query()->get()
            ->sort(fn (Rink $a, Rink $b) => strnatcmp($a->name, $b->name))
            ->groupBy(fn (Rink $rink) => $rink->green())
            ->map(fn (Collection $rinks) => $rinks->values())
            ->all();

        uksort($greens, 'strnatcmp');

        return $greens;
    }

    /**
     * Adds a green with $count rinks, copying the playing times and capacity from an existing
     * rink (or the club defaults on an empty install).
     */
    public function add(string $green, int $count): void
    {
        $green = $this->validName($green);

        if (array_key_exists($green, $this->all())) {
            throw new RuntimeException("Green {$green} already exists.");
        }

        // The subscription is per green, so the licence caps how many there are. Greens already there
        // stay, even when a licence covers fewer (docs/MODULES.md section 5).
        $paid = $this->modules->greens();

        if ($paid !== null && count($this->all()) >= $paid) {
            throw new RuntimeException(
                "Your plan covers {$paid} ".str('green')->plural($paid).'. Contact Bowls Buddy to add another.'
            );
        }

        DB::transaction(function () use ($green, $count) {
            $template = Rink::query()->orderBy('priority')->first();
            $priority = (float) (Rink::query()->max('priority') ?? 0);

            $columns = $template?->only([
                'capacity', 'capacity_heterogenic', 'allow_notes', 'time_start', 'time_end',
                'time_block', 'time_block_bookable', 'time_block_bookable_max',
                'min_range_book', 'range_book', 'max_active_bookings', 'range_cancel',
            ]) ?? [
                'capacity' => (int) config('club.players_per_rink'),
                'capacity_heterogenic' => false,
                'allow_notes' => false,
                'time_start' => config('club.time_start'),
                'time_end' => config('club.time_end'),
                'time_block' => ((int) config('club.slot_minutes')) * 60,
                'time_block_bookable' => ((int) config('club.slot_minutes')) * 60,
                'time_block_bookable_max' => ((int) config('club.slot_minutes')) * 60,
                'min_range_book' => 0,
                'range_book' => ((int) config('club.booking_range_days')) * 86400,
                'max_active_bookings' => 0,
                'range_cancel' => ((int) config('club.cancel_range_hours')) * 3600,
            ];

            for ($number = 1; $number <= $count; $number++) {
                $rink = Rink::query()->create($columns + [
                    'name' => $green.'-'.$number,
                    'status' => 'enabled',
                    'priority' => ++$priority,
                ]);

                $rink->setMeta('capacity-ask-names', $template?->meta('capacity-ask-names') ?? 'optional-names');
            }
        });
    }

    /**
     * Renames a green everywhere: its rinks, its green events and its closed days. Bookings
     * follow their rinks, so nothing else moves.
     */
    public function rename(string $old, string $new): void
    {
        $new = $this->validName($new);
        $rinks = $this->all()[$old] ?? throw new RuntimeException("Green {$old} does not exist.");

        if ($new !== $old && array_key_exists($new, $this->all())) {
            throw new RuntimeException("Green {$new} already exists.");
        }

        DB::transaction(function () use ($old, $new, $rinks) {
            foreach ($rinks as $rink) {
                $suffix = explode('-', $rink->name, 2)[1] ?? $rink->name;
                $rink->update(['name' => $new.'-'.trim($suffix)]);
            }

            foreach ($this->greenEvents($old) as $event) {
                $event->setMeta('green', $new);
            }

            $this->settings->set(
                GreenService::CLOSED_OPTION,
                preg_replace(
                    '/^(\d{4}-\d{2}-\d{2}):'.preg_quote($old, '/').'$/m',
                    '$1:'.$new,
                    (string) $this->settings->get(GreenService::CLOSED_OPTION, ''),
                ),
            );

            $this->settings->set(
                GreenService::DIRECTION_OPTION,
                preg_replace(
                    '/^(\d{4}-\d{2}-\d{2}):'.preg_quote($old, '/').':(\w+)$/m',
                    '$1:'.$new.':$2',
                    (string) $this->settings->get(GreenService::DIRECTION_OPTION, ''),
                ),
            );
        });
    }

    /**
     * Hides a green from members (all its rinks disabled) or shows it again.
     */
    public function setHidden(string $green, bool $hidden): void
    {
        $rinks = $this->all()[$green] ?? throw new RuntimeException("Green {$green} does not exist.");

        foreach ($rinks as $rink) {
            $rink->update(['status' => $hidden ? 'disabled' : 'enabled']);
        }
    }

    /**
     * Deletes a green with its rinks, green events and closed days. Refused while any booking
     * (cancelled ones included, they are the club's history) points at its rinks - hide the
     * green instead - and for the last green.
     */
    public function delete(string $green): void
    {
        $rinks = $this->all()[$green] ?? throw new RuntimeException("Green {$green} does not exist.");

        if (count($this->all()) === 1) {
            throw new RuntimeException('The club needs at least one green.');
        }

        if (Booking::query()->whereIn('sid', $rinks->pluck('sid'))->exists()) {
            throw new RuntimeException(
                "Green {$green} has bookings, which are the club's history. Hide the green instead of deleting it.",
            );
        }

        DB::transaction(function () use ($green, $rinks) {
            Event::query()->whereIn('sid', $rinks->pluck('sid'))->get()->each->delete();
            $this->greenEvents($green)->each->delete();

            foreach ($rinks as $rink) {
                $rink->delete();
            }

            $this->settings->set(
                GreenService::CLOSED_OPTION,
                preg_replace(
                    '/^(\d{4}-\d{2}-\d{2}):'.preg_quote($green, '/').'$/m',
                    '',
                    (string) $this->settings->get(GreenService::CLOSED_OPTION, ''),
                ),
            );

            $this->settings->set(
                GreenService::DIRECTION_OPTION,
                preg_replace(
                    '/^(\d{4}-\d{2}-\d{2}):'.preg_quote($green, '/').':\w+$/m',
                    '',
                    (string) $this->settings->get(GreenService::DIRECTION_OPTION, ''),
                ),
            );
        });
    }

    /** @return Collection<int, Event> the events blocking this whole green */
    private function greenEvents(string $green): Collection
    {
        return Event::query()
            ->whereNull('sid')
            ->with('metaEntries')
            ->get()
            ->filter(fn (Event $event) => $event->meta('green') === $green)
            ->values();
    }

    private function validName(string $green): string
    {
        $green = trim($green);

        if (! preg_match('/^[A-Za-z0-9]{1,10}$/', $green)) {
            throw new RuntimeException('A green name is letters and numbers only, without a dash.');
        }

        return strtoupper($green);
    }
}
