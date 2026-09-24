<?php

use App\Models\Booking;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(ClubSeeder::class));

it('stores, reads and removes meta values', function () {
    $user = User::factory()->create();

    $user->setMeta('firstname', 'Anna');
    expect($user->meta('firstname'))->toBe('Anna');

    $user->setMeta('firstname', 'Annie');
    expect($user->fresh()->meta('firstname'))->toBe('Annie');

    $user->setMeta('firstname', null);
    expect($user->fresh()->meta('firstname', 'none'))->toBe('none');
});

it('prefers the current locale and falls back to the locale-free value', function () {
    $rink = Rink::query()->firstOrFail();
    $rink->setMeta('info.pre', 'Welcome');
    $rink->setMeta('info.pre', 'Welkom', 'af');

    expect($rink->fresh()->meta('info.pre'))->toBe('Welcome');

    app()->setLocale('af');
    expect($rink->fresh()->meta('info.pre'))->toBe('Welkom');
});

it('loads meta for many rows in one query', function () {
    User::factory()->count(5)->create()->each->setMeta('firstname', 'X');

    DB::enableQueryLog();
    $names = User::query()->with('metaEntries')->get()->map->meta('firstname');

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($names->filter()->count())->toBe(User::query()->count());
});

it('stores player names as JSON and still reads the old serialized format', function () {
    $booking = Booking::query()->create([
        'uid' => User::factory()->create()->uid, 'sid' => Rink::query()->value('sid'),
        'status' => 'single', 'visibility' => 'public', 'quantity' => 2,
    ]);

    $booking->setPlayerNames(['Piet Pompies']);
    expect($booking->meta('player-names'))->toBe('["Piet Pompies"]')
        ->and($booking->playerNames())->toBe(['Piet Pompies']);

    $booking->setMeta('player-names', serialize([['name' => 'sb-player-name-2', 'value' => 'Anna Botha']]));
    expect($booking->playerNames())->toBe(['Anna Botha']);

    $booking->setMeta('player-names', 'O:8:"stdClass":0:{}');
    expect($booking->playerNames())->toBe([]);
});

it('knows which rinks an event covers', function () {
    [$a1, $b1] = [Rink::query()->where('name', 'A-1')->first(), Rink::query()->where('name', 'B-1')->first()];
    $times = ['datetime_start' => now(), 'datetime_end' => now()->addHour()];

    $oneRink = Event::query()->create(['sid' => $a1->sid] + $times);
    $greenB = Event::query()->create($times);
    $greenB->setMeta('green', 'B');
    $allRinks = Event::query()->create($times);

    expect($oneRink->covers($a1))->toBeTrue()->and($oneRink->covers($b1))->toBeFalse()
        ->and($greenB->covers($b1))->toBeTrue()->and($greenB->covers($a1))->toBeFalse()
        ->and($allRinks->covers($a1))->toBeTrue()->and($allRinks->covers($b1))->toBeTrue();
});
