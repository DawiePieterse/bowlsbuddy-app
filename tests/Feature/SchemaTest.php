<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the booking tables with the original names and keys', function () {
    foreach ([
        'bs_users' => 'uid', 'bs_users_meta' => 'umid', 'bs_squares' => 'sid', 'bs_squares_meta' => 'smid',
        'bs_bookings' => 'bid', 'bs_bookings_meta' => 'bmid', 'bs_reservations' => 'rid',
        'bs_reservations_meta' => 'rmid', 'bs_events' => 'eid', 'bs_events_meta' => 'emid', 'bs_options' => 'oid',
    ] as $table => $key) {
        expect(Schema::hasTable($table))->toBeTrue()
            ->and(Schema::hasColumn($table, $key))->toBeTrue();
    }
});

it('does not create the unused pricing, product, coupon and bill tables', function () {
    foreach (['bs_squares_pricing', 'bs_squares_products', 'bs_squares_coupons', 'bs_bookings_bills'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});

it('stores any characters in names', function () {
    DB::table('bs_users')->insert(['alias' => 'Thandi 🎳 Nkosi-Müller', 'status' => 'enabled']);

    expect(DB::table('bs_users')->value('alias'))->toBe('Thandi 🎳 Nkosi-Müller');
});

it('rejects unknown status values', function (string $table, array $row) {
    expect(fn () => DB::table($table)->insert($row))->toThrow(QueryException::class);
})->with([
    'user' => ['bs_users', ['alias' => 'X', 'status' => 'superuser']],
    'rink' => ['bs_squares', [
        'name' => 'A-1', 'status' => 'open', 'capacity' => 2, 'capacity_heterogenic' => 0,
        'time_start' => '12:00', 'time_end' => '17:00', 'time_block' => 3600, 'time_block_bookable' => 3600,
    ]],
]);

it('allows each mobile number on one account only', function () {
    DB::table('bs_users')->insert(['alias' => 'Anna', 'status' => 'enabled', 'phone' => '+27821234567']);

    expect(fn () => DB::table('bs_users')->insert(['alias' => 'Other', 'status' => 'enabled', 'phone' => '+27821234567']))
        ->toThrow(QueryException::class);
});
