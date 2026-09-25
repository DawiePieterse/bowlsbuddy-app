<?php

use App\Models\Rink;
use App\Models\User;
use App\Services\BookingRefusal;
use App\Services\BookingRules;
use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

/*
 * The booking rules of docs/PLAN.md, section 5.3, on the LCE setup. "Now" is Monday 5 October 2026, 09:00.
 */

beforeEach(function () {
    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
});

function refusal(string $rink, string $start, ?User $user, int $players = 1, int $hours = 1): ?BookingRefusal
{
    [$from, $until] = slot($start, $hours);

    return app(BookingRules::class)->refusal(rink($rink), $from, $until, $user, $players);
}

describe('rule 1: playing hours, slots and booking range', function () {
    it('accepts a free slot', function () {
        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull()
            ->and(refusal('B-6', '2026-10-06 16:00', member()))->toBeNull();
    });

    it('lets visitors see a free slot as bookable', function () {
        expect(refusal('A-1', '2026-10-06 12:00', null))->toBeNull();
    });

    it('refuses times outside the playing hours', function () {
        expect(refusal('A-1', '2026-10-06 11:00', member()))->toBe(BookingRefusal::OutsidePlayingHours)
            ->and(refusal('A-1', '2026-10-06 17:00', member()))->toBe(BookingRefusal::OutsidePlayingHours)
            ->and(refusal('A-1', '2026-10-06 16:00', member(), hours: 2))->toBe(BookingRefusal::OutsidePlayingHours);
    });

    it('refuses anything but whole slots on the same day', function () {
        [$start] = slot('2026-10-06 12:00');
        $rules = app(BookingRules::class);

        expect(refusal('A-1', '2026-10-06 12:30', member()))->toBe(BookingRefusal::InvalidTime)
            ->and($rules->refusal(rink('A-1'), $start, $start->copy()->addMinutes(30), member()))->toBe(BookingRefusal::InvalidTime)
            ->and($rules->refusal(rink('A-1'), $start, $start, member()))->toBe(BookingRefusal::InvalidTime)
            ->and($rules->refusal(rink('A-1'), $start, $start->copy()->subHour(), member()))->toBe(BookingRefusal::InvalidTime)
            ->and($rules->refusal(rink('A-1'), $start, $start->copy()->addDay(), member()))->toBe(BookingRefusal::InvalidTime);
    });

    it('refuses slots that are over, allowing up to half a slot after the start', function () {
        $this->travelTo(Carbon::parse('2026-10-06 12:30'));
        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull();

        $this->travelTo(Carbon::parse('2026-10-06 12:31'));
        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBe(BookingRefusal::InThePast)
            ->and(refusal('A-1', '2026-10-05 16:00', member()))->toBe(BookingRefusal::InThePast)
            ->and(refusal('A-1', '2026-10-06 13:00', member()))->toBeNull();
    });

    it('lets staff who see the past book it, and staff who see the data book earlier today', function () {
        $this->travelTo(Carbon::parse('2026-10-06 15:00'));

        expect(refusal('A-1', '2026-10-06 12:00', staff('calendar.see-past')))->toBeNull()
            ->and(refusal('A-1', '2026-10-05 12:00', staff('calendar.see-past')))->toBeNull()
            ->and(refusal('A-1', '2026-10-06 12:00', staff('calendar.see-data')))->toBeNull()
            ->and(refusal('A-1', '2026-10-05 12:00', staff('calendar.see-data')))->toBe(BookingRefusal::InThePast)
            ->and(refusal('A-1', '2026-10-06 12:00', User::factory()->admin()->create()))->toBeNull();
    });

    it('refuses dates more than range_book ahead, except for staff who create bookings', function () {
        expect(refusal('A-1', '2026-10-19 12:00', member()))->toBe(BookingRefusal::TooFarAhead)
            ->and(refusal('A-1', '2026-10-18 16:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-19 12:00', staff('calendar.create-single-bookings')))->toBeNull()
            ->and(refusal('A-1', '2026-12-01 12:00', User::factory()->admin()->create()))->toBeNull();
    });

    it('refuses slots within the min_range_book lead time', function () {
        rink('A-1')->update(['min_range_book' => 3 * 3600]);

        expect(refusal('A-1', '2026-10-05 12:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-05 13:00', member()))->toBeNull();

        $this->travelTo(Carbon::parse('2026-10-05 10:30'));

        expect(refusal('A-1', '2026-10-05 12:00', member()))->toBe(BookingRefusal::TooShortNotice)
            ->and(refusal('A-1', '2026-10-05 14:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-05 12:00', staff('calendar.see-past')))->toBe(BookingRefusal::TooShortNotice)
            ->and(refusal('A-1', '2026-10-05 12:00', staff('calendar.see-past', 'calendar.create-single-bookings')))->toBeNull();
    });

    it('refuses more slots at once than time_block_bookable_max, except for staff', function () {
        expect(refusal('A-1', '2026-10-06 12:00', member(), hours: 2))->toBe(BookingRefusal::TooLong)
            ->and(refusal('A-1', '2026-10-06 12:00', staff('calendar.create-single-bookings'), hours: 2))->toBeNull();

        rink('A-1')->update(['time_block_bookable_max' => null]);

        expect(refusal('A-1', '2026-10-06 12:00', member(), hours: 2))->toBeNull();
    });

    it('refuses disabled and read-only rinks, except to staff who create bookings', function (string $status) {
        rink('A-1')->update(['status' => $status]);

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBe(BookingRefusal::RinkUnavailable)
            ->and(refusal('A-1', '2026-10-06 12:00', staff('calendar.create-single-bookings')))->toBeNull();
    })->with(['disabled', 'readonly']);
});

describe('rule 2: players per rink', function () {
    it('allows one booking per slot', function () {
        booked(member(), 'A-1', '2026-10-06 12:00', players: 1);

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBe(BookingRefusal::Occupied)
            ->and(refusal('A-1', '2026-10-06 13:00', member()))->toBeNull()
            ->and(refusal('A-2', '2026-10-06 12:00', member()))->toBeNull();
    });

    it('fills a mixed rink up to its capacity', function () {
        rink('A-1')->update(['capacity_heterogenic' => true]);
        booked(member(), 'A-1', '2026-10-06 12:00', players: 1);

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-06 12:00', member(), players: 2))->toBe(BookingRefusal::TooManyPlayers);

        booked(member(), 'A-1', '2026-10-06 12:00', players: 1);

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBe(BookingRefusal::Occupied);
    });

    it('counts a booking over several slots in each of them', function () {
        booked(member(), 'A-1', '2026-10-06 12:00', hours: 2);

        expect(refusal('A-1', '2026-10-06 13:00', member()))->toBe(BookingRefusal::Occupied)
            ->and(refusal('A-1', '2026-10-06 14:00', member()))->toBeNull();
    });

    it('checks the number of players', function () {
        expect(refusal('A-1', '2026-10-06 12:00', member(), players: 2))->toBeNull()
            ->and(refusal('A-1', '2026-10-06 12:00', member(), players: 3))->toBe(BookingRefusal::TooManyPlayers)
            ->and(refusal('A-1', '2026-10-06 12:00', member(), players: 0))->toBe(BookingRefusal::InvalidPlayers);
    });

    it('ignores cancelled and private bookings', function () {
        booked(member(), 'A-1', '2026-10-06 12:00', status: 'cancelled');
        booked(member(), 'A-1', '2026-10-06 12:00', visibility: 'private');

        expect(refusal('A-1', '2026-10-06 12:00', member(), players: 2))->toBeNull()
            ->and(app(BookingRules::class)->playersBooked(rink('A-1'), ...slot('2026-10-06 12:00')))->toBe(0);
    });
});

describe('rule 3: one rink per member per day', function () {
    it('refuses a second booking on the same day, on any rink', function () {
        $member = member();
        booked($member, 'A-1', '2026-10-06 12:00');

        expect(refusal('B-3', '2026-10-06 15:00', $member))->toBe(BookingRefusal::OneRinkPerDay)
            ->and(refusal('B-3', '2026-10-07 15:00', $member))->toBeNull()
            ->and(refusal('B-3', '2026-10-06 15:00', member()))->toBeNull();
    });

    it('does not count a cancelled booking', function () {
        $member = member();
        booked($member, 'A-1', '2026-10-06 12:00', status: 'cancelled');

        expect(refusal('B-3', '2026-10-06 15:00', $member))->toBeNull();
    });

    it('does not apply to staff who create bookings', function () {
        $secretary = User::factory()->admin()->create();
        $assist = staff('calendar.create-single-bookings');
        booked($secretary, 'A-1', '2026-10-06 12:00');
        booked($assist, 'A-2', '2026-10-06 12:00');

        expect(refusal('B-3', '2026-10-06 15:00', $secretary))->toBeNull()
            ->and(refusal('B-3', '2026-10-06 15:00', $assist))->toBeNull();
    });
});

describe('rule 4: closed greens', function () {
    it('blocks every rink of a closed green for that day, for everyone', function () {
        app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBe(BookingRefusal::GreenClosed)
            ->and(refusal('A-6', '2026-10-06 16:00', User::factory()->admin()->create()))->toBe(BookingRefusal::GreenClosed)
            ->and(refusal('B-1', '2026-10-06 12:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-07 12:00', member()))->toBeNull();

        app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), false);

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull();
    });
});

describe('rule 5: events', function () {
    it('blocks the rink, green or all rinks the event is on', function (?string $on, array $blocked, array $free) {
        blockedBy($on, '2026-10-06 12:00', '2026-10-06 14:00');

        foreach ($blocked as $name) {
            expect(refusal($name, '2026-10-06 13:00', member()))->toBe(BookingRefusal::Event);
        }

        foreach ($free as $name) {
            expect(refusal($name, '2026-10-06 13:00', member()))->toBeNull();
        }
    })->with([
        'one rink' => ['A-1', ['A-1'], ['A-2', 'B-1']],
        'one green' => ['green:B', ['B-1', 'B-6'], ['A-1', 'A-6']],
        'all rinks' => [null, ['A-1', 'B-6'], []],
    ]);

    it('only blocks the slots the event overlaps', function () {
        blockedBy(null, '2026-10-06 13:30', '2026-10-06 14:30');

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-06 13:00', member()))->toBe(BookingRefusal::Event)
            ->and(refusal('A-1', '2026-10-06 14:00', member()))->toBe(BookingRefusal::Event)
            ->and(refusal('A-1', '2026-10-06 15:00', member()))->toBeNull();
    });

    it('ignores disabled events', function () {
        blockedBy(null, '2026-10-06 12:00', '2026-10-06 17:00', status: 'disabled');

        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull();
    });
});

describe('rule 6: hidden days', function () {
    it('hides weekdays and dates from service.calendar.day-exceptions, and shows "+" dates anyway', function () {
        app(Settings::class)->set(BookingRules::DAY_EXCEPTIONS_OPTION, "Sunday\n2026-10-07, 2026-10-08\n+2026-10-18");

        expect(refusal('A-1', '2026-10-11 12:00', member()))->toBe(BookingRefusal::DayHidden)
            ->and(refusal('A-1', '2026-10-07 12:00', member()))->toBe(BookingRefusal::DayHidden)
            ->and(refusal('A-1', '2026-10-08 12:00', member()))->toBe(BookingRefusal::DayHidden)
            ->and(refusal('A-1', '2026-10-18 12:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-06 12:00', member()))->toBeNull()
            ->and(refusal('A-1', '2026-10-11 12:00', User::factory()->admin()->create()))->toBe(BookingRefusal::DayHidden);
    });

    it('hides nothing without day exceptions', function () {
        expect(app(BookingRules::class)->isDayHidden(Carbon::parse('2026-10-11')))->toBeFalse();
    });
});

describe('active booking limit', function () {
    it('limits the bookings a member has open, from the club default, the rink or the member', function () {
        $member = member();
        booked($member, 'A-1', '2026-10-04 12:00');
        booked($member, 'A-1', '2026-10-06 12:00');
        booked($member, 'A-1', '2026-10-07 12:00', status: 'cancelled');

        expect(refusal('A-1', '2026-10-08 12:00', $member))->toBeNull();

        app(Settings::class)->set(BookingRules::MAX_ACTIVE_BOOKINGS_OPTION, '1');
        expect(refusal('A-1', '2026-10-08 12:00', $member))->toBe(BookingRefusal::MaxActiveBookings);

        rink('A-1')->update(['max_active_bookings' => 2]);
        expect(refusal('A-1', '2026-10-08 12:00', $member))->toBeNull()
            ->and(refusal('A-2', '2026-10-08 12:00', $member))->toBe(BookingRefusal::MaxActiveBookings);

        $member->setMeta('max_active_bookings', '1');
        expect(refusal('A-1', '2026-10-08 12:00', $member->fresh()))->toBe(BookingRefusal::MaxActiveBookings);
    });
});

describe('when several rules apply', function () {
    it('gives the same reason the original app shows', function () {
        $member = member();
        booked($member, 'B-1', '2026-10-06 12:00');
        booked(member(), 'A-1', '2026-10-06 12:00');
        blockedBy('A-2', '2026-10-06 12:00', '2026-10-06 13:00');

        // Occupied and an event: "occupied".
        blockedBy('A-1', '2026-10-06 12:00', '2026-10-06 13:00');
        expect(refusal('A-1', '2026-10-06 12:00', member()))->toBe(BookingRefusal::Occupied)
            // Already booked that day and an event: "one rink per day".
            ->and(refusal('A-2', '2026-10-06 12:00', $member))->toBe(BookingRefusal::OneRinkPerDay);

        // Closed green over everything else.
        app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);
        expect(refusal('A-1', '2026-10-06 12:00', $member))->toBe(BookingRefusal::GreenClosed);
    });
});

describe('rule 7: cancelling', function () {
    it('lets members cancel their own booking until range_cancel hours before it starts', function () {
        $member = member();
        $booking = booked($member, 'A-1', '2026-10-06 12:00');
        $rules = app(BookingRules::class);

        $this->travelTo(Carbon::parse('2026-10-05 11:59'));
        expect($rules->canCancel($booking, $member))->toBeTrue()
            ->and($rules->canCancel($booking, member()))->toBeFalse()
            ->and($rules->canCancel($booking, null))->toBeFalse();

        $this->travelTo(Carbon::parse('2026-10-05 12:00'));
        expect($rules->canCancel($booking, $member))->toBeFalse();
    });

    it('lets staff with the privilege cancel any booking at any time', function () {
        $booking = booked(member(), 'A-1', '2026-10-05 12:00');
        $rules = app(BookingRules::class);

        expect($rules->canCancel($booking, staff('calendar.cancel-single-bookings')))->toBeTrue()
            ->and($rules->canCancel($booking, User::factory()->admin()->create()))->toBeTrue()
            ->and($rules->canCancel($booking, staff('calendar.create-single-bookings')))->toBeFalse();
    });

    it('does not let members cancel when the rink has no cancel range, or twice', function () {
        $member = member();
        $cancelled = booked($member, 'A-1', '2026-10-10 12:00', status: 'cancelled');
        $booking = booked($member, 'A-2', '2026-10-10 12:00');
        $rules = app(BookingRules::class);

        expect($rules->canCancel($cancelled, $member))->toBeFalse();

        Rink::query()->where('name', 'A-2')->update(['range_cancel' => null]);

        expect($rules->canCancel($booking->fresh(), $member))->toBeFalse();
    });
});

describe('rule 10: privileges', function () {
    it('gives admins every privilege and assists only those granted to them', function () {
        $admin = User::factory()->admin()->create();
        $assist = staff('calendar.see-data');
        $member = member();

        foreach (array_keys(User::PRIVILEGES) as $privilege) {
            expect($admin->hasPrivilege($privilege))->toBeTrue()
                ->and($member->hasPrivilege($privilege))->toBeFalse()
                ->and($assist->hasPrivilege($privilege))->toBe($privilege === 'calendar.see-data');
        }
    });
});
