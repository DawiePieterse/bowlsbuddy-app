<?php

use App\Filament\Pages\Utilisation;
use App\Models\User;
use App\Services\GreenService;
use App\Services\RinkUtilisation;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();
});

/** Bookings over the last weeks, with green A played East-West on 30 Sep and North-South on 1 Oct. */
function utilisationFixture(): void
{
    $greens = app(GreenService::class);
    $greens->setDirection('A', Carbon::parse('2026-09-30'), 'EW');
    $greens->setDirection('A', Carbon::parse('2026-10-01'), 'NS');

    $member = member();

    booked($member, 'A-1', '2026-09-30 14:00');                 // East-West, 1 h
    booked($member, 'A-1', '2026-10-01 12:00', hours: 2);       // North-South, 2 h
    booked($member, 'A-2', '2026-10-01 15:00');                 // North-South, 1 h
    booked($member, 'A-3', '2026-10-01 15:00', status: 'cancelled');
    booked($member, 'B-1', '2026-10-03 16:00');                 // no direction indicated
    booked($member, 'A-1', '2026-09-20 12:00', hours: 3);       // last month, not last week
}

it('counts booked hours per rink, column and direction of play', function () {
    utilisationFixture();

    $heatmap = app(RinkUtilisation::class)->for('week');

    expect($heatmap['from']->toDateString())->toBe('2026-09-29')
        ->and($heatmap['until']->toDateString())->toBe('2026-10-05')
        ->and(array_column($heatmap['columns'], 'key'))->toHaveCount(7)
        ->and($heatmap['columns'][0]['label'])->toBe('Tue 29');

    $a = $heatmap['greens']['A'];
    $ns = $a['directions']['NS'];
    $ew = $a['directions']['EW'];

    expect($a['max'])->toBe(2.0)
        ->and($ns['days'])->toBe(1)
        ->and($ns['total'])->toBe(3.0)
        ->and($ns['rinks'][0]['rink']->name)->toBe('A-1')
        ->and($ns['rinks'][0]['hours']['2026-10-01'])->toBe(2.0)
        ->and($ns['rinks'][0]['total'])->toBe(2.0)
        ->and($ns['rinks'][1]['hours']['2026-10-01'])->toBe(1.0)
        ->and($ns['rinks'][2]['total'])->toBe(0.0) // cancelled
        ->and($ew['days'])->toBe(1)
        ->and($ew['rinks'][0]['hours']['2026-09-30'])->toBe(1.0)
        ->and($ew['rinks'][0]['hours']['2026-10-01'])->toBe(0.0)
        ->and($a['directions']['']['total'])->toBe(0.0);

    $b = $heatmap['greens']['B'];

    expect($b['directions']['']['days'])->toBe(1)
        ->and($b['directions']['']['rinks'][0]['hours']['2026-10-03'])->toBe(1.0)
        ->and($b['directions']['NS']['total'])->toBe(0.0);
});

it('widens the columns with the period: days, weeks, months', function () {
    utilisationFixture();
    $utilisation = app(RinkUtilisation::class);

    $month = $utilisation->for('month');
    expect($month['from']->toDateString())->toBe('2026-09-06')
        ->and($month['columns'])->toHaveCount(30)
        ->and($month['greens']['A']['directions']['']['rinks'][0]['hours']['2026-09-20'])->toBe(3.0)
        ->and($month['greens']['A']['max'])->toBe(3.0);

    $quarter = $utilisation->for('quarter');
    expect($quarter['from']->toDateString())->toBe('2026-07-06')
        ->and($quarter['columns'][0])->toBe(['key' => '2026-07-06', 'label' => '6 Jul'])
        ->and(end($quarter['columns'])['key'])->toBe('2026-10-05')
        ->and($quarter['greens']['A']['directions']['NS']['rinks'][0]['hours']['2026-09-28'])->toBe(2.0);

    $year = $utilisation->for('year');
    expect($year['from']->toDateString())->toBe('2025-10-06')
        ->and($year['columns'])->toHaveCount(13)
        ->and($year['columns'][0]['label'])->toBe('Oct')
        ->and($year['greens']['A']['directions']['EW']['rinks'][0]['hours']['2026-09'])->toBe(1.0)
        ->and($year['greens']['A']['directions']['']['rinks'][0]['hours']['2026-09'])->toBe(3.0);

    expect(fn () => $utilisation->for('decade'))->toThrow(InvalidArgumentException::class);
});

it('shows the Secretary the heatmap per direction and switches the period', function () {
    utilisationFixture();

    $this->actingAs($this->admin)->get('/admin/utilisation')
        ->assertOk()
        ->assertSee('Green A')
        ->assertSee('North-South')
        ->assertSee('East-West')
        ->assertSee('Direction not indicated')
        ->assertSee('Sun 6 Sep 2026 to Mon 5 Oct 2026');

    Livewire::test(Utilisation::class)
        ->assertSet('period', 'month')
        ->call('setPeriod', 'week')
        ->assertSet('period', 'week')
        ->assertSee('Tue 29 Sep 2026 to Mon 5 Oct 2026')
        ->assertSee('No bookings played')
        ->call('setPeriod', 'decade')
        ->assertSet('period', 'week');
});

it('keeps the utilisation page from members and staff without the bookings privilege', function () {
    $this->actingAs(member())->get('/admin/utilisation')->assertForbidden();
    $this->actingAs(staff('admin.see-menu'))->get('/admin/utilisation')->assertForbidden();
});

it('shows the utilisation page to staff with the bookings privilege', function () {
    $this->actingAs(staff('admin.see-menu', 'admin.booking'))->get('/admin/utilisation')
        ->assertOk()
        ->assertSee('No bookings in this period');
});
