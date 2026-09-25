<?php

/*
 * Booking scenarios run through both the original app (scripts/reference/capture.php, which records
 * tests/Reference/reference-values.json) and this app (tests/Feature/ReferenceValuesTest.php), so the
 * booking rules can be compared with how the original behaves. See docs/PLAN.md, section 5.3.
 *
 * Every scenario starts from the LCE setup (greens A and B, rinks A-1 to B-6, 12:00 to 17:00 in 60-minute
 * slots, 2 players, 14 days ahead, cancel up to 24 hours before) with members anna, ben and cara and the
 * Secretary (admin). Days are counted from the day the values were captured (0 is that day).
 *
 * setup:  ['booking', member, rink, day, time, (players, status, visibility, hours, label)]
 *         ['event', on (rink, "green:X" or null for all), day, from, to, name]
 *         ['closed', green, day]
 *         ['option', key, value]  where {date:N} is the date and {weekday:N} the weekday name of day N
 *         ['rink', name, [column => value]]
 *         ['user-meta', member, key, value]
 * checks: ['book', as, rink, day, time, (hours, players)]  as: member, admin, visitor or
 *         "assist:privilege,privilege"
 *         ['cancel', as, booking label]
 * With 'overview' => true the greens overview is recorded too.
 */

return [
    'free-slots' => [
        'setup' => [],
        'checks' => [
            ['book', 'anna', 'A-1', 1, '12:00'],
            ['book', 'anna', 'B-6', 1, '16:00'],
            ['book', 'visitor', 'A-1', 1, '12:00'],
            ['book', 'anna', 'A-1', 1, '12:00', 1, 2],
            ['book', 'anna', 'A-1', 1, '12:00', 1, 3],
        ],
        'overview' => true,
    ],

    'playing-hours' => [
        'setup' => [],
        'checks' => [
            ['book', 'anna', 'A-1', 1, '11:00'],
            ['book', 'anna', 'A-1', 1, '17:00'],
            ['book', 'anna', 'A-1', 1, '16:00', 2],
            ['book', 'admin', 'A-1', 1, '11:00'],
        ],
    ],

    'past-slots' => [
        'setup' => [],
        'checks' => [
            ['book', 'anna', 'A-1', -1, '16:00'],
            ['book', 'anna', 'A-1', 0, '12:00'],
            ['book', 'anna', 'A-1', 0, '13:00'],
            ['book', 'anna', 'A-1', 0, '14:00'],
            ['book', 'anna', 'A-1', 0, '15:00'],
            ['book', 'anna', 'A-1', 0, '16:00'],
            ['book', 'admin', 'A-1', -1, '16:00'],
            ['book', 'assist:calendar.see-past', 'A-1', -1, '16:00'],
            ['book', 'assist:calendar.see-data', 'A-1', -1, '16:00'],
            ['book', 'assist:calendar.see-data', 'A-1', 0, '12:00'],
        ],
        'overview' => true,
    ],

    'booking-range' => [
        'setup' => [],
        'checks' => [
            ['book', 'anna', 'A-1', 13, '16:00'],
            ['book', 'anna', 'A-1', 14, '16:00'],
            ['book', 'anna', 'A-1', 15, '12:00'],
            ['book', 'admin', 'A-1', 15, '12:00'],
            ['book', 'assist:calendar.create-single-bookings', 'A-1', 30, '12:00'],
        ],
    ],

    'lead-time' => [
        'setup' => [
            ['rink', 'A-1', ['min_range_book' => 2 * 86400]],
        ],
        'checks' => [
            ['book', 'anna', 'A-1', 1, '12:00'],
            ['book', 'anna', 'A-1', 3, '12:00'],
            ['book', 'anna', 'A-2', 1, '12:00'],
            ['book', 'assist:calendar.see-past', 'A-1', 1, '12:00'],
            ['book', 'admin', 'A-1', 1, '12:00'],
        ],
    ],

    'slots-at-once' => [
        'setup' => [
            ['rink', 'A-2', ['time_block_bookable_max' => null]],
        ],
        'checks' => [
            ['book', 'anna', 'A-1', 1, '12:00', 2],
            ['book', 'admin', 'A-1', 1, '12:00', 2],
            ['book', 'anna', 'A-2', 1, '12:00', 2],
        ],
    ],

    'disabled-rink' => [
        'setup' => [
            ['rink', 'A-1', ['status' => 'disabled']],
        ],
        'checks' => [
            ['book', 'anna', 'A-1', 1, '12:00'],
            ['book', 'admin', 'A-1', 1, '12:00'],
            ['book', 'anna', 'A-2', 1, '12:00'],
        ],
        'overview' => true,
    ],

    'players-per-rink' => [
        'setup' => [
            ['booking', 'anna', 'A-1', 1, '12:00'],
            ['booking', 'anna', 'A-2', 2, '12:00', 1, 'cancelled'],
            ['booking', 'anna', 'A-3', 1, '12:00', 1, 'single', 'private'],
            ['rink', 'B-1', ['capacity_heterogenic' => 1]],
            ['booking', 'anna', 'B-1', 3, '12:00'],
            ['booking', 'ben', 'B-2', 3, '13:00', 1, 'single', 'public', 2],
        ],
        'checks' => [
            ['book', 'ben', 'A-1', 1, '12:00'],
            ['book', 'ben', 'A-1', 1, '13:00'],
            ['book', 'ben', 'A-2', 2, '12:00', 1, 2],
            ['book', 'ben', 'A-3', 1, '12:00', 1, 2],
            ['book', 'ben', 'B-1', 3, '12:00'],
            ['book', 'ben', 'B-1', 3, '12:00', 1, 2],
            ['book', 'cara', 'B-2', 3, '14:00'],
            ['book', 'cara', 'B-2', 3, '15:00'],
        ],
        'overview' => true,
    ],

    'one-rink-per-day' => [
        'setup' => [
            ['booking', 'anna', 'A-1', 1, '12:00'],
            ['booking', 'ben', 'A-2', 1, '12:00', 1, 'cancelled'],
            ['booking', 'admin', 'A-3', 1, '12:00'],
        ],
        'checks' => [
            ['book', 'anna', 'B-3', 1, '15:00'],
            ['book', 'anna', 'B-3', 2, '15:00'],
            ['book', 'ben', 'B-3', 1, '15:00'],
            ['book', 'admin', 'B-3', 1, '15:00'],
            ['book', 'cara', 'B-3', 1, '15:00'],
        ],
    ],

    'closed-green' => [
        'setup' => [
            ['closed', 'A', 1],
            ['booking', 'anna', 'A-1', 1, '12:00'],
        ],
        'checks' => [
            ['book', 'ben', 'A-2', 1, '12:00'],
            ['book', 'ben', 'A-1', 1, '12:00'],
            ['book', 'admin', 'A-6', 1, '16:00'],
            ['book', 'ben', 'B-1', 1, '12:00'],
            ['book', 'ben', 'A-1', 2, '12:00'],
        ],
        'overview' => true,
    ],

    'events' => [
        'setup' => [
            ['event', 'A-1', 1, '12:00', '14:00', 'Coaching'],
            ['event', 'green:B', 2, '13:30', '14:30', 'League'],
            ['event', null, 3, '12:00', '17:00', 'Club day'],
            ['booking', 'anna', 'A-2', 1, '12:00'],
            ['event', 'A-2', 1, '12:00', '13:00', 'Maintenance'],
        ],
        'checks' => [
            ['book', 'ben', 'A-1', 1, '13:00'],
            ['book', 'ben', 'A-1', 1, '14:00'],
            ['book', 'ben', 'A-3', 1, '13:00'],
            ['book', 'ben', 'B-1', 2, '13:00'],
            ['book', 'ben', 'B-6', 2, '14:00'],
            ['book', 'ben', 'B-6', 2, '15:00'],
            ['book', 'ben', 'A-1', 2, '13:00'],
            ['book', 'ben', 'A-6', 3, '16:00'],
            ['book', 'admin', 'B-1', 3, '12:00'],
            ['book', 'ben', 'A-2', 1, '12:00'],
            ['book', 'anna', 'A-1', 1, '12:00'],
        ],
        'overview' => true,
    ],

    'hidden-days' => [
        'setup' => [
            ['option', 'service.calendar.day-exceptions', "{weekday:2}\n{date:3}, {date:4}\n+{date:9}"],
        ],
        'checks' => [
            ['book', 'anna', 'A-1', 1, '12:00'],
            ['book', 'anna', 'A-1', 2, '12:00'],
            ['book', 'anna', 'A-1', 3, '12:00'],
            ['book', 'anna', 'A-1', 4, '12:00'],
            ['book', 'anna', 'A-1', 9, '12:00'],
            ['book', 'admin', 'A-1', 2, '12:00'],
        ],
        'overview' => true,
    ],

    'active-booking-limit' => [
        'setup' => [
            ['option', 'service.user.default.max_active_bookings', '1'],
            ['booking', 'anna', 'A-1', 1, '12:00'],
            ['booking', 'anna', 'A-1', -2, '12:00'],
            ['booking', 'cara', 'A-1', 2, '12:00', 1, 'cancelled'],
            ['rink', 'B-1', ['max_active_bookings' => 2]],
            ['user-meta', 'ben', 'max_active_bookings', '3'],
            ['booking', 'ben', 'A-2', 1, '12:00'],
            ['booking', 'ben', 'A-2', 2, '12:00'],
        ],
        'checks' => [
            ['book', 'anna', 'A-1', 3, '12:00'],
            ['book', 'anna', 'B-1', 3, '12:00'],
            ['book', 'ben', 'A-1', 3, '12:00'],
            ['book', 'cara', 'A-1', 3, '12:00'],
        ],
    ],

    'several-reasons' => [
        'setup' => [
            ['booking', 'anna', 'B-1', 1, '12:00'],
            ['booking', 'ben', 'A-1', 1, '12:00'],
            ['event', 'A-1', 1, '12:00', '13:00', 'Coaching'],
            ['event', 'A-2', 1, '12:00', '13:00', 'Coaching'],
            ['closed', 'B', 2],
            ['booking', 'ben', 'B-2', 2, '12:00'],
        ],
        'checks' => [
            ['book', 'cara', 'A-1', 1, '12:00'],
            ['book', 'anna', 'A-2', 1, '12:00'],
            ['book', 'anna', 'A-1', 1, '12:00'],
            ['book', 'cara', 'B-2', 2, '12:00'],
        ],
    ],

    'cancelling' => [
        'setup' => [
            ['booking', 'anna', 'A-1', 3, '12:00', 1, 'single', 'public', 1, 'later'],
            ['booking', 'anna', 'A-1', 0, '16:00', 1, 'single', 'public', 1, 'today'],
            ['rink', 'A-3', ['range_cancel' => null]],
            ['booking', 'anna', 'A-3', 4, '12:00', 1, 'single', 'public', 1, 'no-range'],
        ],
        'checks' => [
            ['cancel', 'anna', 'later'],
            ['cancel', 'ben', 'later'],
            ['cancel', 'visitor', 'later'],
            ['cancel', 'anna', 'today'],
            ['cancel', 'admin', 'today'],
            ['cancel', 'assist:calendar.cancel-single-bookings', 'today'],
            ['cancel', 'anna', 'no-range'],
        ],
    ],
];
