<?php

namespace App\Console\Commands;

use App\Services\ClubSetup;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Sets up a new club in minutes on hosting with a command line (PLAN.md section 7). Hosts
 * without one use the first-run setup page instead.
 */
class ClubCreate extends Command
{
    protected $signature = 'club:create';

    protected $description = 'Set up a new club: name, greens, rinks, playing times and the Secretary';

    public function handle(ClubSetup $setup): int
    {
        if ($setup->isSetUp()) {
            $this->error('This install already has a club. Use a fresh database for a new club.');

            return self::FAILURE;
        }

        $club = [
            'name' => text('Club name', default: 'LCE Bowls Club', required: true),
            'short_name' => text('Short name', default: 'LCE', required: true),
            'greens' => array_map('trim', explode(',', text('Greens (comma-separated letters)', default: 'A,B', required: true))),
            'rinks_per_green' => (int) text('Rinks per green', default: '6', required: true),
            'time_start' => text('First slot starts (HH:MM)', default: '12:00', required: true),
            'time_end' => text('Last slot ends (HH:MM)', default: '17:00', required: true),
            'slot_minutes' => (int) text('Slot length in minutes', default: '60', required: true),
            'players_per_rink' => (int) text('Players per rink', default: '2', required: true),
            'booking_range_days' => (int) text('Bookable ahead (days)', default: '14', required: true),
            'cancel_range_hours' => (int) text('Cancel cut-off (hours)', default: '24', required: true),
            'admin_email' => text('Secretary email address', required: true, validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Not a valid email address.'),
            'admin_password' => null,
        ];

        if (! confirm("Create {$club['name']} with ".count($club['greens'])." greens x {$club['rinks_per_green']} rinks?")) {
            return self::FAILURE;
        }

        [$admin, $password] = $setup->create($club);

        $this->info("Done. The Secretary logs in at /admin as {$admin->email} with the password: {$password}");
        $this->comment('They should change it right away under Profile.');

        return self::SUCCESS;
    }
}
