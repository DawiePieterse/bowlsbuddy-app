<?php

use App\Models\Rink;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;

beforeEach(fn () => config(['club.admin_password' => 'a-good-password']));

it('sets up greens A and B with six rinks each', function () {
    $this->seed(ClubSeeder::class);

    $rinks = Rink::query()->orderBy('priority')->get();

    expect($rinks->pluck('name')->all())->toBe(['A-1', 'A-2', 'A-3', 'A-4', 'A-5', 'A-6', 'B-1', 'B-2', 'B-3', 'B-4', 'B-5', 'B-6'])
        ->and($rinks->map->green()->unique()->values()->all())->toBe(['A', 'B']);

    $rink = $rinks->first();

    expect($rink->time_start)->toBe('12:00:00')
        ->and($rink->time_end)->toBe('17:00:00')
        ->and($rink->time_block)->toBe(3600)
        ->and($rink->capacity)->toBe(2)
        ->and($rink->range_book)->toBe(14 * 86400);
});

it('creates the Secretary as admin and the club settings', function () {
    $this->seed(ClubSeeder::class);

    $admin = User::query()->where('email', 'secretary@example.com')->firstOrFail();

    expect($admin->status)->toBe('admin')
        ->and($admin->hasPrivilege('admin.config'))->toBeTrue()
        ->and(app(Settings::class)->get('client.name.full'))->toBe('LCE Bowls Club');
});

it('does nothing when the club is already set up', function () {
    $this->seed(ClubSeeder::class);
    $this->seed(ClubSeeder::class);

    expect(Rink::query()->count())->toBe(12)
        ->and(User::query()->count())->toBe(1);
});
