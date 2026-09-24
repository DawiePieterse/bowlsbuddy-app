<?php

/*
 * Starting setup for a new club, used by the ClubSeeder (and later the first-run setup page and
 * `club:create`). Everything here can be changed afterwards in the admin panel.
 */
return [

    'name' => env('CLUB_NAME', 'LCE Bowls Club'),
    'short_name' => env('CLUB_SHORT_NAME', 'LCE'),

    'greens' => explode(',', env('CLUB_GREENS', 'A,B')),
    'rinks_per_green' => (int) env('CLUB_RINKS_PER_GREEN', 6),

    'time_start' => env('CLUB_TIME_START', '12:00'),
    'time_end' => env('CLUB_TIME_END', '17:00'),
    'slot_minutes' => (int) env('CLUB_SLOT_MINUTES', 60),
    'players_per_rink' => (int) env('CLUB_PLAYERS_PER_RINK', 2),
    'booking_range_days' => (int) env('CLUB_BOOKING_RANGE_DAYS', 14),
    'cancel_range_hours' => (int) env('CLUB_CANCEL_RANGE_HOURS', 24),

    // First admin (the Club Secretary). Without a password, a random one is printed by the seeder.
    'admin_email' => env('CLUB_ADMIN_EMAIL', 'secretary@example.com'),
    'admin_password' => env('CLUB_ADMIN_PASSWORD'),

];
