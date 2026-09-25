<?php

namespace Database\Seeders;

use App\Models\Rink;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sets up a new club from config/club.php: names, greens and rinks, and the first admin (the Secretary).
 * Does nothing if rinks already exist, so it is safe to run twice.
 */
class ClubSeeder extends Seeder
{
    public function run(Settings $settings): void
    {
        if (Rink::query()->exists()) {
            $this->command->warn('Rinks already exist; club setup skipped.');

            return;
        }

        $club = config('club');

        foreach ([
            'client.name.full' => $club['name'],
            'client.name.short' => $club['short_name'],
            'service.name.full' => 'Bowls Buddy',
            'service.name.short' => 'BB',
            'service.meta.description' => 'Rink bookings',
            'subject.square.type' => 'Rink',
            'subject.square.type.plural' => 'Rinks',
            'subject.square.unit' => 'Player',
            'subject.square.unit.plural' => 'Players',
            'subject.type' => 'our club',
            'service.user.registration' => 'true',
            'service.user.activation' => 'manual',
            'service.calendar.days' => '1',
            'service.calendar.day-exceptions' => '',
            'service.greens.closed' => '',
        ] as $key => $value) {
            $settings->set($key, $value);
        }

        $slot = $club['slot_minutes'] * 60;
        $priority = 1;

        foreach ($club['greens'] as $green) {
            for ($number = 1; $number <= $club['rinks_per_green']; $number++) {
                $rink = Rink::query()->create([
                    'name' => trim($green).'-'.$number,
                    'status' => 'enabled',
                    'priority' => $priority++,
                    'capacity' => $club['players_per_rink'],
                    'capacity_heterogenic' => false,
                    'allow_notes' => false,
                    'time_start' => $club['time_start'],
                    'time_end' => $club['time_end'],
                    'time_block' => $slot,
                    'time_block_bookable' => $slot,
                    'time_block_bookable_max' => $slot,
                    'min_range_book' => 0,
                    'range_book' => $club['booking_range_days'] * 86400,
                    'max_active_bookings' => 0,
                    'range_cancel' => $club['cancel_range_hours'] * 3600,
                ]);

                $rink->setMeta('capacity-ask-names', 'optional-names');
            }
        }

        $password = $club['admin_password'] ?: Str::password(16, symbols: false);

        $admin = User::query()->create([
            'alias' => 'Club Secretary',
            'status' => 'admin',
            'phone' => $club['admin_phone'],
            'email' => $club['admin_email'] ?: null,
            'pw' => $password,
        ]);

        $admin->setMeta('firstname', 'Club');
        $admin->setMeta('lastname', 'Secretary');

        if (! $club['admin_password']) {
            $this->command->info("Admin {$admin->phone} created with password: {$password}");
        }
    }
}
