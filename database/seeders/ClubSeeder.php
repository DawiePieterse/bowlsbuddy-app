<?php

namespace Database\Seeders;

use App\Services\ClubSetup;
use Illuminate\Database\Seeder;

/**
 * Sets up a new club from config/club.php: names, greens and rinks, and the first admin (the
 * Secretary). Does nothing if the club already exists, so it is safe to run twice.
 */
class ClubSeeder extends Seeder
{
    public function run(ClubSetup $setup): void
    {
        if ($setup->isSetUp()) {
            $this->command->warn('The club is already set up; seeding skipped.');

            return;
        }

        /** @var array{name: string, short_name: string, greens: list<string>, rinks_per_green: int,
         *     time_start: string, time_end: string, slot_minutes: int, players_per_rink: int,
         *     booking_range_days: int, cancel_range_hours: int, admin_email: string,
         *     admin_password: ?string} $club */
        $club = config('club');

        [$admin, $password] = $setup->create($club);

        if (! $club['admin_password']) {
            $this->command->info("Admin {$club['admin_email']} created with password: {$password}");
        }
    }
}
