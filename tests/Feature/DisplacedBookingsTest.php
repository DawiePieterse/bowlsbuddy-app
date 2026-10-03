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

it('finds nothing while every green is open', function () {
    booked(bowler('Jan', 'Botha'), 'A-1', '2026-10-06 12:00');

    expect($this->displaced->upcoming())->toBe([])
        ->and($this->displaced->memberCount())->toBe(0);
});

it('lists the bookings on a green closed for the day, with a WhatsApp message per member', function () {
    $jan = bowler('Jan', 'Botha', '+27821234567');
    $booking = booked($jan, 'A-1', '2026-10-06 12:00', players: 2);
    $booking->setPlayerNames(['Piet Pompies']);
    booked(bowler('Anna', 'Venter', '+27831112222'), 'B-1', '2026-10-06 12:00'); // green B stays open

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

    $items = $this->displaced->upcoming();
    expect($items)->toHaveCount(1)
        ->and($items[0]['booking']->bid)->toBe($booking->bid)
        ->and($items[0]['reason'])->toBe('green A is closed that day');

    [$message] = $this->displaced->messages();
    expect($message['name'])->toBe('Jan Botha')
        ->and($message['phone'])->toBe('+27821234567')
        ->and($message['text'])->toBe("Hi Jan, your booking of rink A-1 on Tue 6 Oct, 12:00–13:00 at LCE can't go ahead: "
            .'green A is closed that day. Please let Piet Pompies know. Sorry for the inconvenience.')
        ->and($message['url'])->toBe('https://wa.me/27821234567?text='.rawurlencode($message['text']));
});

it('leaves out cancelled bookings, bookings that have started and other days', function () {
    $jan = bowler('Jan', 'Botha');
    booked($jan, 'A-1', '2026-10-05 12:00');                      // started an hour ago
    booked($jan, 'A-2', '2026-10-06 12:00', status: 'cancelled');  // cancelled
    booked($jan, 'A-3', '2026-10-07 12:00');                      // another day

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-05'), true);
    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);

    expect($this->displaced->upcoming())->toBe([]);
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

it('puts all of a member\'s affected bookings in one message', function () {
    $jan = bowler('Jan', 'Botha');
    booked($jan, 'A-1', '2026-10-06 12:00');
    booked($jan, 'A-2', '2026-10-07 15:00')->setPlayerNames(['Piet Pompies']);
    booked(bowler('Anna', 'Venter', null), 'A-3', '2026-10-06 14:00');

    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-06'), true);
    app(GreenService::class)->setClosed('A', Carbon::parse('2026-10-07'), true);

    $messages = $this->displaced->messages();

    expect($messages)->toHaveCount(2)
        ->and($this->displaced->memberCount())->toBe(2)
        ->and($messages[0]['text'])->toBe("Hi Jan, these bookings of yours at LCE can't go ahead:\n"
            ."- rink A-1 on Tue 6 Oct, 12:00–13:00: green A is closed that day\n"
            ."- rink A-2 on Wed 7 Oct, 15:00–16:00: green A is closed that day\n"
            .'Please let your playing partners know. Sorry for the inconvenience.')
        // Anna has no cellphone number, so there is nothing to tap
        ->and($messages[1]['name'])->toBe('Anna Venter')
        ->and($messages[1]['url'])->toBeNull();
});

it('narrows the list to one green on one day, or to one event', function () {
    $member = bowler('Jan', 'Botha');
    booked($member, 'A-1', '2026-10-06 12:00');
    booked(bowler('Anna', 'Venter', '+27831112222'), 'B-1', '2026-10-06 12:00');
    booked($member, 'B-2', '2026-10-07 12:00');

    $event = blockedBy(null, '2026-10-06 12:00', '2026-10-06 13:00', 'Open day');
    blockedBy('B-2', '2026-10-07 12:00', '2026-10-07 13:00', 'Repairs');

    expect($this->displaced->upcoming('A', Carbon::parse('2026-10-06')))->toHaveCount(1)
        ->and($this->displaced->upcoming('B'))->toHaveCount(2)
        ->and($this->displaced->forEvent($event))->toHaveCount(2);
});
