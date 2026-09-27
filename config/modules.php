<?php

/*
 * Bowls Buddy modules and licences (docs/MODULES.md). Every club runs the same code; a licence signed
 * by Bowls Buddy decides which modules an install switches on. Add a module here first, then gate its
 * pages, routes and jobs with App\Support\Licensing\Modules.
 */
return [

    /*
     * Every module, key => name, description and the modules it needs. "bookings" is the base plan
     * and always on, licensed or not, so members can always book.
     */
    'modules' => [
        'bookings' => [
            'name' => 'Bookings',
            'description' => 'Rink bookings, greens, events, the day sheet and utilisation.',
            'requires' => [],
        ],
        'rollups' => [
            'name' => 'Roll-ups',
            'description' => 'Members put their names down for a session; teams are drawn and rinks assigned.',
            'requires' => ['bookings'],
        ],
        'competitions' => [
            'name' => 'Competitions',
            'description' => 'Club championships: entries, draws, knock-out and round-robin, results and honours board.',
            'requires' => ['bookings'],
        ],
        'leagues' => [
            'name' => 'Leagues',
            'description' => 'League and inter-club fixtures, team selection and player confirmations.',
            'requires' => ['competitions'],
        ],
        'comms' => [
            'name' => 'Communication',
            'description' => 'Noticeboard, broadcasts to members, and notices when a green closes.',
            'requires' => [],
        ],
        'membership' => [
            'name' => 'Membership',
            'description' => 'Membership categories, annual fees and reminders.',
            'requires' => [],
        ],
        'payments' => [
            'name' => 'Payments',
            'description' => 'Online payment of subscriptions and visitor green fees.',
            'requires' => ['membership'],
        ],
        'visitors' => [
            'name' => 'Visitors',
            'description' => 'Booking requests from visitors and corporate days, approved by the Secretary.',
            'requires' => ['bookings'],
        ],
        'greenkeeping' => [
            'name' => 'Greenkeeping',
            'description' => 'Maintenance log, planned direction of play and rink wear.',
            'requires' => ['bookings'],
        ],
        'coaching' => [
            'name' => 'Coaching',
            'description' => 'Coaching slots, lesson bookings and progress of new members.',
            'requires' => ['bookings'],
        ],
        'equipment' => [
            'name' => 'Equipment',
            'description' => 'Locker allocation and a loan register for club bowls and mats.',
            'requires' => [],
        ],
        'functions' => [
            'name' => 'Functions',
            'description' => 'Social events with RSVPs, and clubhouse hire.',
            'requires' => [],
        ],
        'stats' => [
            'name' => 'Statistics',
            'description' => 'Playing history, results and club rankings.',
            'requires' => ['competitions'],
        ],
        'governance' => [
            'name' => 'Governance',
            'description' => 'Document store, AGM nominations and voting.',
            'requires' => [],
        ],
    ],

    /* Days a module keeps working after the licence expires, with a banner for the Secretary. */
    'grace_days' => 14,

    /*
     * Ed25519 public keys that sign licences, key id => base64 key. Generate a pair with
     * `php artisan licence:keygen`, add the public key here, and keep the private key off club servers.
     * An old key stays listed until every licence it signed has been reissued.
     */
    'public_keys' => [
        //
    ],

    /* Only set where licences are issued (your own machine), never on a club server. */
    'private_key' => env('BB_LICENCE_PRIVATE_KEY'),
    'private_key_id' => env('BB_LICENCE_KEY_ID'),

    /* Where to ask for a licence or another module, shown on the Licence page. */
    'contact' => 'WhatsApp 083 456 5646',

];
