<?php

use App\Filament\Pages\Greens;
use App\Models\Event;
use App\Models\Rink;
use App\Models\User;
use App\Services\GreenManager;
use App\Services\GreenService;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
    $this->manager = app(GreenManager::class);
});

it('adds a green with rinks copied from the existing setup', function () {
    $this->manager->add('c', 4);

    $rinks = Rink::query()->with('metaEntries')->where('name', 'like', 'C-%')->orderBy('priority')->get();

    expect($rinks->pluck('name')->all())->toBe(['C-1', 'C-2', 'C-3', 'C-4'])
        ->and($rinks->first()->time_start)->toBe('12:00:00')
        ->and($rinks->first()->capacity)->toBe(2)
        ->and($rinks->first()->range_book)->toBe(14 * 86400)
        ->and($rinks->first()->meta('capacity-ask-names'))->toBe('optional-names')
        ->and((float) $rinks->first()->priority)->toBeGreaterThan((float) Rink::query()->where('name', 'B-6')->firstOrFail()->priority)
        ->and(array_keys($this->manager->all()))->toBe(['A', 'B', 'C']);
});

it('refuses to add a green that exists or has a bad name', function () {
    expect(fn () => $this->manager->add('A', 2))->toThrow(RuntimeException::class, 'already exists')
        ->and(fn () => $this->manager->add('X-1', 2))->toThrow(RuntimeException::class, 'letters and numbers');
});

it('renames a green everywhere: rinks, green events, closed days and directions of play', function () {
    $event = Event::query()->create([
        'sid' => null, 'status' => 'enabled',
        'datetime_start' => '2026-10-06 12:00:00', 'datetime_end' => '2026-10-06 17:00:00',
    ]);
    $event->setMeta('green', 'A');
    app(GreenService::class)->setClosed('A', now()->addDays(3), true);
    app(GreenService::class)->setClosed('B', now()->addDays(3), true);
    app(GreenService::class)->setDirection('A', now()->subDays(3), 'NS');
    app(GreenService::class)->setDirection('B', now()->subDays(3), 'EW');

    $this->manager->rename('A', 'Main');

    expect(array_keys($this->manager->all()))->toBe(['B', 'MAIN'])
        ->and(Rink::query()->where('name', 'MAIN-1')->exists())->toBeTrue()
        ->and($event->fresh()->meta('green'))->toBe('MAIN')
        ->and(app(GreenService::class)->isClosed('MAIN', now()->addDays(3)))->toBeTrue()
        ->and(app(GreenService::class)->isClosed('B', now()->addDays(3)))->toBeTrue()
        ->and(app(GreenService::class)->direction('MAIN', now()->subDays(3)))->toBe('NS')
        ->and(app(GreenService::class)->direction('A', now()->subDays(3)))->toBeNull()
        ->and(app(GreenService::class)->direction('B', now()->subDays(3)))->toBe('EW');
});

it('keeps bookings attached to their renamed rinks', function () {
    $member = member();
    $booking = booked($member, 'A-1', now()->addDay()->format('Y-m-d').' 14:00');

    $this->manager->rename('A', 'C');

    expect($booking->fresh()->rink->name)->toBe('C-1');
});

it('hides a green from members and shows it again', function () {
    $this->manager->setHidden('A', true);

    expect(array_keys(app(GreenService::class)->greens()))->toBe(['B'])
        ->and(array_keys($this->manager->all()))->toBe(['A', 'B']);

    $this->manager->setHidden('A', false);

    expect(array_keys(app(GreenService::class)->greens()))->toBe(['A', 'B']);
});

it('deletes an empty green with its events, closed days and directions of play', function () {
    $event = Event::query()->create([
        'sid' => Rink::query()->where('name', 'B-2')->firstOrFail()->sid,
        'status' => 'enabled',
        'datetime_start' => '2026-10-06 12:00:00', 'datetime_end' => '2026-10-06 17:00:00',
    ]);
    app(GreenService::class)->setClosed('B', now()->addDays(3), true);
    app(GreenService::class)->setDirection('A', now()->subDays(3), 'NS');
    app(GreenService::class)->setDirection('B', now()->subDays(3), 'EW');

    $this->manager->delete('B');

    expect(array_keys($this->manager->all()))->toBe(['A'])
        ->and(Event::query()->whereKey($event->eid)->exists())->toBeFalse()
        ->and((string) app(Settings::class)->get(GreenService::CLOSED_OPTION))->not->toContain(':B')
        ->and((string) app(Settings::class)->get(GreenService::DIRECTION_OPTION))->not->toContain(':B:')
        ->and(app(GreenService::class)->direction('A', now()->subDays(3)))->toBe('NS');
});

it('refuses to delete a green with bookings, or the last green', function () {
    booked(member(), 'B-1', now()->addDay()->format('Y-m-d').' 14:00');

    expect(fn () => $this->manager->delete('B'))->toThrow(RuntimeException::class, 'Hide the green instead');

    $this->manager->delete('A');

    expect(fn () => $this->manager->delete('B'))->toThrow(RuntimeException::class, 'at least one green');
});

it('manages greens from the admin page', function () {
    $admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    $this->actingAs($admin)->get('/admin/greens')->assertOk()->assertSee('Green A')->assertSee('Add a green');

    Livewire::test(Greens::class)
        ->callAction('addGreen', ['name' => 'C', 'rinks' => 2])
        ->assertNotified('Green added')
        ->callAction('renameGreen', ['green' => 'C', 'name' => 'D'])
        ->assertNotified('Green renamed')
        ->callAction('hideGreen', ['green' => 'D', 'visibility' => 'hide'])
        ->assertNotified('Green hidden from members')
        ->callAction('deleteGreen', ['green' => 'D'])
        ->assertNotified('Green deleted');

    expect(array_keys(app(GreenManager::class)->all()))->toBe(['A', 'B']);
});

it('keeps staff without admin.config off the greens page', function () {
    $assist = staff('admin.see-menu');

    $this->actingAs($assist)->get('/admin/greens')->assertForbidden();
});
