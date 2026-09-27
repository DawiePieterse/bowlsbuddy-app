<?php

namespace App\Console\Commands;

use App\Services\GreenManager;
use App\Support\Licensing\Modules;
use Illuminate\Console\Command;

/** Shows the installed licence and the state of every module. */
class LicenceShow extends Command
{
    protected $signature = 'licence:show';

    protected $description = 'Show the Bowls Buddy licence and modules of this install';

    public function handle(Modules $modules, GreenManager $greens): int
    {
        $licence = $modules->licence();

        if ($modules->problem() !== null) {
            $this->warn('The installed licence can\'t be used: '.$modules->problem());
        }

        if ($licence === null) {
            $this->line('No licence: bookings only.');
        } else {
            $this->line("Club: {$licence->club}");
            $this->line('Plan: '.($licence->plan ?: '-').($licence->trial ? ' (trial)' : ''));
            $this->line("Valid until: {$licence->expires->toDateString()}");
            $this->line('Greens: '.count($greens->all()).' in use, '.($licence->greens ?? 'no limit').' paid for');
        }

        $this->table(['Module', 'State'], array_map(
            fn (string $key, array $module) => [$module['name']." ({$key})", $modules->state($key)->label()],
            array_keys($modules->all()),
            $modules->all(),
        ));

        return self::SUCCESS;
    }
}
