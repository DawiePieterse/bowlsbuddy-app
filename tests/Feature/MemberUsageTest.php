<?php

use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\User;
use App\Services\MemberUsage;
use App\Services\RinkUtilisation;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    Carbon::setTestNow('2026-10-05 13:00');
    $this->seed(ClubSeeder::class);
    $this->admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    $this->anna = member();
    $this->anna->update(['alias' => 'Anna']);
    $this->ben = member();
    $this->ben->update(['alias' => 'Ben']);
    $this->carl = member();
    $this->carl->update(['alias' => 'Carl']);   // books nothing

    booked($this->anna, 'A-1', '2026-10-01 12:00', hours: 2);
    booked($this->anna, 'A-2', '2026-10-02 12:00');
    booked($this->anna, 'A-3', '2026-10-03 12:00', status: 'cancelled');
    booked($this->ben, 'B-1', '2026-10-04 14:00', hours: 3);
    booked($this->ben, 'B-2', '2026-09-20 12:00', hours: 2);   // last month, not last week
    booked($this->carl, 'B-3', '2026-08-01 12:00');             // last quarter only
});

it('counts hours and bookings per member in the period, cancelled bookings left out', function () {
    $usage = app(MemberUsage::class);
    [$from, $until] = RinkUtilisation::range('week');

    $rows = $usage->applyTo(User::query(), $from, $until)->orderByDesc('usage_hours')->get();

    expect($rows->pluck('alias')->all())->toBe(['Anna', 'Ben'])
        ->and((float) $rows[0]->usage_hours)->toBe(3.0)
        ->and((int) $rows[0]->usage_bookings)->toBe(2)
        ->and((float) $rows[1]->usage_hours)->toBe(3.0)
        ->and($usage->totals($from, $until))->toBe(['hours' => 6.0, 'bookings' => 3, 'members' => 2]);

    expect($usage->totals(...RinkUtilisation::range('month')))->toBe(['hours' => 8.0, 'bookings' => 4, 'members' => 2])
        ->and($usage->totals(...RinkUtilisation::range('quarter')))->toBe(['hours' => 9.0, 'bookings' => 5, 'members' => 3]);
});

it('ranks members by hours with their share of the total on the use of rinks tab', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListMembers::class)
        ->set('activeTab', 'usage')
        ->assertSet('usagePeriod', 'month')
        ->assertCanSeeTableRecords([$this->ben, $this->anna], inOrder: true)
        ->assertCanNotSeeTableRecords([$this->carl, $this->admin])
        ->assertSee('Share of total')
        ->assertSee('62.5%')   // Ben: 5 of 8 hours
        ->assertSee('37.5%')   // Anna: 3 of 8 hours
        ->assertSee(['8 h in 4 bookings', 'by 2 members'])
        ->call('setUsagePeriod', 'week')
        ->assertSet('usagePeriod', 'week')
        ->assertSee('50.0%')
        ->assertSee(['6 h in 3 bookings', 'by 2 members'])
        ->call('setUsagePeriod', 'quarter')
        ->assertCanSeeTableRecords([$this->ben, $this->anna, $this->carl], inOrder: true)
        ->call('setUsagePeriod', 'decade')
        ->assertSet('usagePeriod', 'quarter');
});

it('keeps the other member tabs listing everyone by name', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListMembers::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords([$this->anna, $this->ben, $this->carl, $this->admin->fresh()])
        ->assertDontSee('Share of total')
        ->assertSee('Membership');

    $this->get('/admin/members?tab=usage&usagePeriod=year')
        ->assertOk()
        ->assertSee('Use of rinks')
        ->assertSee('Share of total');
});
