<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The booking tables, with the same names, keys and columns as the original ep-3 Bookingsystem schema
 * (bowlsbuddy/data/db/ep3-bs.sql). Differences, all deliberate (see docs/PLAN.md, section 5.2):
 *
 * - utf8mb4 everywhere (set by the connection).
 * - Composite indexes matching the queries the app runs.
 * - bs_users.remember_token for "remember me".
 * - bs_users.email is unique (several NULLs are still allowed for members without a login).
 * - The unused pricing, products, coupons and bills tables are not created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_users', function (Blueprint $table) {
            $table->increments('uid');
            $table->string('alias', 128)->index();
            $table->string('status', 64)->default('placeholder')
                ->comment('placeholder|deleted|blocked|disabled|enabled|assist|admin');
            $table->string('email', 128)->nullable()->unique();
            $table->string('pw', 256)->nullable();
            $table->rememberToken();
            $table->unsignedTinyInteger('login_attempts')->nullable();
            $table->dateTime('login_detent')->nullable();
            $table->dateTime('last_activity')->nullable();
            $table->string('last_ip', 64)->nullable();
            $table->dateTime('created')->nullable();
        });

        $this->createMetaTable('bs_users_meta', 'umid', 'uid', 'bs_users');

        Schema::create('bs_squares', function (Blueprint $table) {
            $table->increments('sid');
            $table->string('name', 64);
            $table->string('status', 64)->default('enabled')->comment('disabled|readonly|enabled');
            $table->float('priority')->default(1);
            $table->unsignedInteger('capacity');
            $table->boolean('capacity_heterogenic');
            $table->boolean('allow_notes')->default(false);
            $table->time('time_start');
            $table->time('time_end');
            $table->unsignedInteger('time_block')->comment('seconds');
            $table->unsignedInteger('time_block_bookable')->comment('seconds');
            $table->unsignedInteger('time_block_bookable_max')->nullable()->comment('seconds');
            $table->unsignedInteger('min_range_book')->nullable()->default(0)->comment('seconds');
            $table->unsignedInteger('range_book')->nullable()->comment('seconds');
            $table->unsignedInteger('max_active_bookings')->nullable()->default(0);
            $table->unsignedInteger('range_cancel')->nullable()->comment('seconds');
        });

        $this->createMetaTable('bs_squares_meta', 'smid', 'sid', 'bs_squares', withLocale: true);

        Schema::create('bs_bookings', function (Blueprint $table) {
            $table->increments('bid');
            $table->unsignedInteger('uid');
            $table->unsignedInteger('sid');
            $table->string('status', 64)->comment('single|cancelled');
            $table->string('status_billing', 64)->default('pending')->comment('pending|paid|cancelled|uncollectable');
            $table->string('visibility', 64)->default('public')->comment('public|private');
            $table->unsignedInteger('quantity');
            $table->dateTime('created');

            $table->foreign('uid')->references('uid')->on('bs_users');
            $table->foreign('sid')->references('sid')->on('bs_squares');
            $table->index(['sid', 'status']);
            $table->index(['uid', 'status']);
        });

        $this->createMetaTable('bs_bookings_meta', 'bmid', 'bid', 'bs_bookings');

        Schema::create('bs_reservations', function (Blueprint $table) {
            $table->increments('rid');
            $table->unsignedInteger('bid');
            $table->date('date');
            $table->time('time_start');
            $table->time('time_end');

            $table->foreign('bid')->references('bid')->on('bs_bookings')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['date', 'time_start']);
        });

        $this->createMetaTable('bs_reservations_meta', 'rmid', 'rid', 'bs_reservations');

        Schema::create('bs_events', function (Blueprint $table) {
            $table->increments('eid');
            $table->unsignedInteger('sid')->nullable()->comment('NULL for a whole green (meta "green") or all rinks');
            $table->string('status', 64)->default('enabled')->comment('enabled|disabled');
            $table->dateTime('datetime_start');
            $table->dateTime('datetime_end');
            $table->unsignedInteger('capacity')->nullable();

            $table->foreign('sid')->references('sid')->on('bs_squares');
            $table->index(['datetime_start', 'datetime_end']);
        });

        $this->createMetaTable('bs_events_meta', 'emid', 'eid', 'bs_events', withLocale: true);

        Schema::create('bs_options', function (Blueprint $table) {
            $table->increments('oid');
            $table->string('key', 64);
            $table->text('value');
            $table->string('locale', 8)->nullable();

            $table->index(['key', 'locale']);
        });
    }

    public function down(): void
    {
        foreach ([
            'bs_options', 'bs_events_meta', 'bs_events', 'bs_reservations_meta', 'bs_reservations',
            'bs_bookings_meta', 'bs_bookings', 'bs_squares_meta', 'bs_squares', 'bs_users_meta', 'bs_users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** Key/value table belonging to one row of $ownerTable; removed together with it. */
    private function createMetaTable(string $table, string $key, string $ownerKey, string $ownerTable, bool $withLocale = false): void
    {
        Schema::create($table, function (Blueprint $blueprint) use ($key, $ownerKey, $ownerTable, $withLocale) {
            $blueprint->increments($key);
            $blueprint->unsignedInteger($ownerKey);
            $blueprint->string('key', 64);
            $blueprint->text('value');

            if ($withLocale) {
                $blueprint->string('locale', 8)->nullable();
            }

            $blueprint->foreign($ownerKey)->references($ownerKey)->on($ownerTable)->cascadeOnDelete()->cascadeOnUpdate();
            $blueprint->index([$ownerKey, 'key']);
            $blueprint->index('key');
        });
    }
};
