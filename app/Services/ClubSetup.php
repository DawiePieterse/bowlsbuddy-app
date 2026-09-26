<?php

namespace App\Services;

use App\Models\Rink;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sets up a new club: site settings, greens and rinks, and the first admin (the Secretary).
 * Used by the ClubSeeder, the club:create command and the first-run setup page, so all three
 * create exactly the same club (PLAN.md Phase 4).
 */
class ClubSetup
{
    public function __construct(private Settings $settings) {}

    public function isSetUp(): bool
    {
        return User::query()->exists() || Rink::query()->exists();
    }

    /**
     * @param  array{name: string, short_name: string, greens: list<string>, rinks_per_green: int,
     *     time_start: string, time_end: string, slot_minutes: int, players_per_rink: int,
     *     booking_range_days: int, cancel_range_hours: int, admin_email: string,
     *     admin_password: ?string}  $club
     * @return array{User, string} the Secretary and their password
     */
    public function create(array $club): array
    {
        return DB::transaction(function () use ($club) {
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
                'service.user.activation' => 'immediate',
                'service.calendar.days' => '1',
                'service.calendar.day-exceptions' => '',
                'service.greens.closed' => '',
            ] as $key => $value) {
                $this->settings->set($key, $value);
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
                'email' => $club['admin_email'],
                'pw' => $password,
            ]);

            $admin->setMeta('firstname', 'Club');
            $admin->setMeta('lastname', 'Secretary');

            return [$admin, $password];
        });
    }
}
