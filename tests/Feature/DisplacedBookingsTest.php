<?php

use App\Models\User;
use App\Services\BookingRules;
use App\Services\DisplacedBookings;
use App\Services\GreenManager;
use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
    app(Settings::class)->set('client.name.short', 'LCE');
    $this->displaced = app(DisplacedBookings::class);
});

function bowler(string $first, string $last, ?string $phone = '+27821234567'): User
{
    $user = User::factory()->create(['alias' => "$first $last", 'phone' => $phone]);
    $user->setMeta('firstname', $first);
    $user->setMeta('lastname', $last);

    return $user->fresh();
}

it('finds and cancels nothing while every green is open', function () {
    $booking = booked(bowler('Jan', 'Botha'), 'A-1', '2026-10-06 12:00');

    expect($this->displaced->upcoming())->toBe([])
        ->and($this->displaced->cancelDisplaced())->toBe([])
        ->and($booking->fresh()->status)->toBe('single');
});

it('cancels the bookings on a green closed for the day, noting why', function () {
    $jan = bowler('Jan', 'Botha', '+27821234567');
    $booking = booked($jan, 'A-1', '2026-10-06 12:00', players: 2);
    $booking->setPlayerNames(['Piet Pompies']);
    $other = booked(bowler('Anna', 'Venter', '+27831112222'), 'B-1', '2026-10-06 12:00'); // green B stays open

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

    $cancelled = $this->displaced->cancelDisplaced();

    expect($cancelled)->toHaveCount(1)
        ->and($cancelled[0]['booking']->bid)->toBe($booking->bid)
        ->and($booking->fresh()->status)->toBe('cancelled')
        ->and($booking->fresh()->meta(DisplacedBookings::REASON))->toBe('green A is closed that day')
        ->and($other->fresh()->status)->toBe('single')
        ->and($this->displaced->upcoming())->toBe([]); // nothing left to cancel

    [$message] = $this->displaced->messages();
    expect($message['name'])->toBe('Jan Botha')
        ->and($message['phone'])->toBe('+27821234567')
        ->and($message['bookings'])->toBe((string) $booking->bid)
        ->and($message['told'])->toBeFalse()
        ->and($message['text'])->toBe('Hi Jan, your booking of rink A-1 on Tue 6 Oct, 12:00–13:00 at LCE has been cancelled because '
            .'green A is closed that day. Sorry for the inconvenience. Please let Piet Pompies know. '
            .'You can book another rink at '.route('home'))
        ->and($message['url'])->toBe('https://wa.me/27821234567?text='.rawurlencode($message['text']));
});

it('leaves bookings that have started, other days and bookings members cancelled themselves', function () {
    $jan = bowler('Jan', 'Botha');
    $started = booked($jan, 'A-1', '2026-10-05 12:00');                // started an hour ago
    $ownCancel = booked($jan, 'A-2', '2026-10-06 12:00', status: 'cancelled');
    $otherDay = booked($jan, 'A-3', '2026-10-07 12:00');

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-05'), true);
    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

    expect($this->displaced->cancelDisplaced())->toBe([])
        ->and($started->fresh()->status)->toBe('single')
        ->and($otherDay->fresh()->status)->toBe('single')
        ->and($this->displaced->cancelled())->toBe([]) // the member's own cancellation isn't a closure
        ->and($ownCancel->fresh()->meta(DisplacedBookings::REASON))->toBeNull();
});

it('gives the reason for each kind of closure', function () {
    $member = bowler('Jan', 'Botha');
    booked($member, 'A-1', '2026-10-06 12:00');
    booked($member, 'B-1', '2026-10-07 14:00');
    booked($member, 'A-2', '2026-10-08 12:00');
    booked($member, 'B-2', '2026-10-09 12:00');

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);
    blockedBy('green:B', '2026-10-07 13:00', '2026-10-07 17:00', 'Club league');
    app(Settings::class)->set(BookingRules::DAY_EXCEPTIONS_OPTION, '2026-10-08');
    blockedBy('A-1', '2026-10-09 12:00', '2026-10-09 13:00', 'Rink A-1 only'); // not B-2's rink

    expect(array_column($this->displaced->upcoming(), 'reason'))->toBe([
        'green A is closed that day',
        'the rink is set aside for Club league',
        'the club is closed that day',
    ]);

    app(GreenManager::class)->setHidden('B', true);

    expect(array_column($this->displaced->upcoming(), 'reason'))->toBe([
        'green A is closed that day',
        'rink B-1 is closed until further notice',
        'the club is closed that day',
        'rink B-2 is closed until further notice',
    ]);
});

it('puts all of a member\'s cancelled bookings in one message', function () {
    $jan = bowler('Jan', 'Botha');
    booked($jan, 'A-1', '2026-10-06 12:00');
    booked($jan, 'A-2', '2026-10-07 15:00')->setPlayerNames(['Piet Pompies']);
    booked(bowler('Anna', 'Venter', null), 'A-3', '2026-10-06 14:00');

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);
    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-07'), true);
    $this->displaced->cancelDisplaced();

    $messages = $this->displaced->messages();

    expect($messages)->toHaveCount(2)
        ->and($this->displaced->memberCount())->toBe(2)
        ->and($messages[0]['text'])->toBe("Hi Jan, these bookings of yours at LCE have been cancelled:\n"
            ."- rink A-1 on Tue 6 Oct, 12:00–13:00: green A is closed that day\n"
            ."- rink A-2 on Wed 7 Oct, 15:00–16:00: green A is closed that day\n"
            .'Sorry for the inconvenience. Please let your playing partners know. You can book another rink at '.route('home'))
        // Anna has no cellphone number, so there is nothing to tap
        ->and($messages[1]['name'])->toBe('Anna Venter')
        ->and($messages[1]['url'])->toBeNull();
});

it('notes members as told, which takes them off the count', function () {
    $jan = bowler('Jan', 'Botha');
    $first = booked($jan, 'A-1', '2026-10-06 12:00');
    $second = booked(bowler('Anna', 'Venter', '+27831112222'), 'A-2', '2026-10-06 12:00');
    $ownCancel = booked($jan, 'B-1', '2026-10-07 12:00', status: 'cancelled');

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);
    $this->displaced->cancelDisplaced();

    $this->displaced->markTold([$first->bid, $ownCancel->bid]);

    expect($this->displaced->memberCount())->toBe(1)
        ->and(array_column($this->displaced->messages(), 'told'))->toBe([true, false])
        ->and($ownCancel->fresh()->meta(DisplacedBookings::TOLD))->toBeNull(); // not a closure's

    $this->displaced->markTold([$second->bid]);

    expect($this->displaced->memberCount())->toBe(0)
        ->and($this->displaced->messages())->toHaveCount(2); // still listed, as told
});

it('narrows the cancelled bookings to one green on one day', function () {
    $member = bowler('Jan', 'Botha');
    booked($member, 'A-1', '2026-10-06 12:00');
    booked(bowler('Anna', 'Venter', '+27831112222'), 'B-1', '2026-10-06 12:00');
    booked($member, 'B-2', '2026-10-07 12:00');

    blockedBy(null, '2026-10-06 12:00', '2026-10-06 13:00', 'Open day');
    blockedBy('B-2', '2026-10-07 12:00', '2026-10-07 13:00', 'Repairs');
    $this->displaced->cancelDisplaced();

    expect($this->displaced->cancelled('A', Carbon::parse('2026-10-06')))->toHaveCount(1)
        ->and($this->displaced->cancelled('B'))->toHaveCount(2)
        ->and($this->displaced->cancelled())->toHaveCount(3)
        ->and($this->displaced->bookedCount('A', Carbon::parse('2026-10-06')))->toBe(0);
});
