<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Rink;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Made-up members and bookings for local development and screenshots. Never run in production.
 * Every demo member's password is "secret123"; their mobile numbers are 082 000 0001 to 082 000 0008.
 */
class DemoSeeder extends Seeder
{
    private const MEMBERS = [
        ['Jan', 'van der Merwe'], ['Anna', 'Botha'], ['Piet', 'Pompies'], ['Susan', 'Naidoo'],
        ['Johan', 'Venter'], ['Thandi', 'Mokoena'], ['Mike', 'Smith'], ['Elsa', 'Kruger'],
    ];

    public function run(): void
    {
        $members = [];

        foreach (self::MEMBERS as $index => [$first, $last]) {
            $member = User::query()->firstOrCreate(
                ['phone' => sprintf('+2782000%04d', $index + 1)],
                ['alias' => "$first $last", 'status' => 'enabled', 'email' => strtolower($first).'@example.com', 'pw' => 'secret123'],
            );

            $member->setMeta('firstname', $first);
            $member->setMeta('lastname', $last);
            $members[] = $member;
        }

        $rinks = Rink::query()->pluck('sid', 'name');
        $day = Carbon::tomorrow();

        // [rink, hour, booked by (index), partner (index or null)]
        foreach ([['A-1', 12, 0, 2], ['A-2', 12, 1, 3], ['A-3', 13, 4, 5], ['B-1', 14, 6, 7], ['B-4', 15, 3, null]] as [$rink, $hour, $by, $with]) {
            $booking = Booking::query()->create([
                'uid' => $members[$by]->uid,
                'sid' => $rinks[$rink],
                'status' => 'single',
                'visibility' => 'public',
                'quantity' => $with === null ? 1 : 2,
            ]);

            $booking->reservations()->create([
                'date' => $day->toDateString(),
                'time_start' => sprintf('%02d:00', $hour),
                'time_end' => sprintf('%02d:00', $hour + 1),
            ]);

            if ($with !== null) {
                $booking->setPlayerNames([$members[$with]->alias]);
            }
        }
    }
}
