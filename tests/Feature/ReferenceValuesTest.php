<?php

use App\Models\Booking;
use App\Models\User;
use App\Services\BookingRefusal;
use App\Services\BookingRules;
use App\Services\GreenService;
use App\Services\GreensOverview;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;

/*
 * Runs the scenarios in tests/Reference/scenarios.php through this app and expects the answers the
 * original app gave (tests/Reference/reference-values.json, recorded by scripts/reference/capture.php).
 * "Now" is the moment the values were recorded, so past and future slots match too.
 *
 * Where this app deliberately says it differently, both answers count as the same:
 * - A slot inside the min_range_book lead time: the original says "already over", this app "too soon".
 * - A slot outside the playing hours: the original says "invalid time", this app "outside the playing hours".
 * - An event: the original says "occupied", this app "taken by an event".
 */

const SAME_ANSWER = [
    'too-short-notice' => 'in-the-past',
    'outside-playing-hours' => 'invalid-time',
    'event' => 'occupied',
];

function referenceValues(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../Reference/reference-values.json'), true);
}

it('answers like the original app', function (string $name) {
    $scenario = (require __DIR__.'/../Reference/scenarios.php')[$name];
    $reference = referenceValues();
    $expected = $reference['scenarios'][$name];

    $this->seed(ClubSeeder::class);
    $this->travelTo(Carbon::parse($reference['captured_at']));

    $today = CarbonImmutable::today();
    $day = fn (int $offset) => $today->addDays($offset);
    $users = ['admin' => User::factory()->admin()->create()];
    $bookings = [];

    foreach (['anna', 'ben', 'cara'] as $alias) {
        $users[$alias] = member();
    }

    foreach ($scenario['setup'] as $step) {
        match ($step[0]) {
            'booking' => (function () use ($step, $users, $day, &$bookings) {
                [, $member, $rink, $offset, $time] = $step;
                [$players, $status, $visibility, $hours, $label] = array_slice($step, 5) + [1, 'single', 'public', 1, null];

                $bookings[$label] = booked($users[$member], $rink, $day($offset)->toDateString()." $time", $players, $status, $visibility, $hours);
            })(),
            'event' => blockedBy($step[1], $day($step[2])->toDateString()." $step[3]", $day($step[2])->toDateString()." $step[4]", $step[5]),
            'closed' => app(GreenService::class)->setClosed($step[1], $day($step[2]), true),
            'option' => app(Settings::class)->set($step[1], preg_replace_callback('/\{(date|weekday):(-?\d+)\}/', fn ($match) => $match[1] === 'date'
                ? $day((int) $match[2])->toDateString()
                : $day((int) $match[2])->englishDayOfWeek, $step[2])),
            'rink' => rink($step[1])->update($step[2]),
            'user-meta' => $users[$step[1]]->setMeta($step[2], $step[3]),
        };
    }

    $rules = app(BookingRules::class);
    $answers = [];

    foreach ($scenario['checks'] as $check) {
        $as = $check[1];

        if ($as !== 'visitor' && ! isset($users[$as])) {
            $users[$as] = staff(...explode(',', substr($as, 7)));
        }

        $user = $as === 'visitor' ? null : $users[$as]->fresh();

        if ($check[0] === 'cancel') {
            $answers[] = $rules->canCancel(Booking::query()->findOrFail($bookings[$check[2]]->bid), $user) ? 'cancellable' : 'not-cancellable';

            continue;
        }

        [, , $rink, $offset, $time] = $check;
        [$hours, $players] = array_slice($check, 5) + [1, 1];
        [$start, $end] = slot($day($offset)->toDateString()." $time", $hours);

        $refusal = $rules->refusal(rink($rink), $start, $end, $user, $players);
        $answers[] = $refusal === null ? 'booked' : (SAME_ANSWER[$refusal->value] ?? $refusal->value);
    }

    $expectedAnswers = array_map(fn (array $check) => SAME_ANSWER[$check['outcome']] ?? $check['outcome'], $expected['checks']);

    expect(array_map(null, $scenario['checks'], $answers))->toBe(array_map(null, $scenario['checks'], $expectedAnswers));

    if (isset($expected['overview'])) {
        $overview = array_map(fn (array $day) => ['date' => $day['date']->toDateString()] + array_diff_key($day, ['date' => 0]),
            app(GreensOverview::class)->days());

        expect($overview)->toBe($expected['overview']);
    }
})->with(fn () => array_keys(referenceValues()['scenarios']));

it('has reference values for every scenario', function () {
    expect(array_keys(referenceValues()['scenarios']))
        ->toBe(array_keys(require __DIR__.'/../Reference/scenarios.php'));
});

it('knows every answer the original app gave', function () {
    $known = array_merge(array_column(BookingRefusal::cases(), 'value'), ['booked', 'cancellable', 'not-cancellable']);

    foreach (referenceValues()['scenarios'] as $scenario) {
        foreach ($scenario['checks'] as $check) {
            expect($known)->toContain($check['outcome']);
        }
    }
});
