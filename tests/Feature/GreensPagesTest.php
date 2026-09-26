<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00'); // a Monday
    $this->seed(ClubSeeder::class);
});

function pageBooking(User $user, string $rinkName, string $date, string $from, string $to, array $names = []): Booking
{
    $booking = Booking::query()->create([
        'uid' => $user->uid,
        'sid' => Rink::query()->where('name', $rinkName)->firstOrFail()->sid,
        'status' => 'single',
        'visibility' => 'public',
        'quantity' => $names ? count($names) + 1 : 1,
    ]);

    $booking->reservations()->create(['date' => $date, 'time_start' => $from, 'time_end' => $to]);

    if ($names) {
        $booking->setPlayerNames($names);
    }

    return $booking;
}

it('shows 14 playing days with both greens on the overview', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Greens')
        ->assertSee('30 of 30 free')
        ->assertSee('Green A')
        ->assertSee('Green B');
});

it('shows a closed green and an event day on the overview', function () {
    app(Settings::class)->set('service.greens.closed', '2026-10-06:A');

    $event = Event::query()->create([
        'sid' => null, 'status' => 'enabled',
        'datetime_start' => '2026-10-07 12:00:00', 'datetime_end' => '2026-10-07 17:00:00',
    ]);
    $event->setMeta('name', 'Club competition');
    $event->setMeta('green', 'B');

    $this->get('/')
        ->assertOk()
        ->assertSee('closed')
        ->assertSee('Club competition');
});

it('shows the calendar with free slots to guests without names', function () {
    $member = User::factory()->create(['alias' => 'Jane Bowler']);
    $member->setMeta('firstname', 'Jane');
    $member->setMeta('lastname', 'Bowler');
    pageBooking($member, 'A-1', '2026-10-06', '14:00:00', '15:00:00', ['Pat Partner']);

    $this->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Green A')
        ->assertSee('A-1')
        ->assertSee('A-6')
        ->assertSee('Booked')
        ->assertDontSee('Jane')
        ->assertDontSee('Pat Partner')
        ->assertSee('Log in');
});

it('shows player names and own bookings to members', function () {
    $jane = User::factory()->create(['alias' => 'Jane Bowler']);
    $jane->setMeta('firstname', 'Jane');
    $jane->setMeta('lastname', 'Bowler');
    pageBooking($jane, 'A-1', '2026-10-06', '14:00:00', '15:00:00', ['Pat Partner']);

    $me = User::factory()->create();
    pageBooking($me, 'A-2', '2026-10-06', '15:00:00', '16:00:00');

    $response = $this->actingAs($me)->get('/greens/A/2026-10-06')->assertOk();

    $response->assertSee('Jane Bowler')
        ->assertSee('Pat Partner')
        ->assertSee('slot-own', false)
        ->assertSee('Book');
});

it('marks event slots purple with the event name', function () {
    $event = Event::query()->create([
        'sid' => Rink::query()->where('name', 'A-1')->firstOrFail()->sid,
        'status' => 'enabled',
        'datetime_start' => '2026-10-06 13:00:00',
        'datetime_end' => '2026-10-06 15:00:00',
    ]);
    $event->setMeta('name', 'Maintenance');

    $this->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('slot-event', false)
        ->assertSee('Maintenance');
});

it('shows a closed green banner and no bookable slots', function () {
    app(Settings::class)->set('service.greens.closed', '2026-10-06:A');

    $this->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('Green A is closed on this day.')
        ->assertDontSee('slot-free');
});

it('links the previous and next playing days', function () {
    $this->get('/greens/A/2026-10-06')
        ->assertOk()
        ->assertSee('/greens/A/2026-10-05')
        ->assertSee('/greens/A/2026-10-07')
        ->assertSee('/greens/B/2026-10-06');
});

it('rejects an unknown green or malformed date', function () {
    $this->get('/greens/Z/2026-10-06')->assertNotFound();
    $this->get('/greens/A/not-a-date')->assertNotFound();
});
