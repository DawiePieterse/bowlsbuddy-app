<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Only allow the known values in the status columns. MySQL 8.0.16+ and MariaDB 10.2+ enforce CHECK
 * constraints; other databases (SQLite in quick local runs) skip this migration.
 */
return new class extends Migration
{
    private const CHECKS = [
        'bs_users' => ['status', ['placeholder', 'deleted', 'blocked', 'disabled', 'enabled', 'assist', 'admin']],
        'bs_squares' => ['status', ['disabled', 'readonly', 'enabled']],
        'bs_bookings' => ['status', ['single', 'cancelled']],
        'bs_events' => ['status', ['enabled', 'disabled']],
    ];

    public function up(): void
    {
        if (! $this->supported()) {
            return;
        }

        foreach (self::CHECKS as $table => [$column, $values]) {
            $list = implode(', ', array_map(fn ($value) => DB::getPdo()->quote($value), $values));

            DB::statement("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_{$column}_check` CHECK (`$column` IN ($list))");
        }

        DB::statement("ALTER TABLE `bs_bookings` ADD CONSTRAINT `bs_bookings_visibility_check` CHECK (`visibility` IN ('public', 'private'))");
    }

    public function down(): void
    {
        if (! $this->supported()) {
            return;
        }

        foreach (self::CHECKS as $table => [$column]) {
            DB::statement("ALTER TABLE `$table` DROP CONSTRAINT `{$table}_{$column}_check`");
        }

        DB::statement('ALTER TABLE `bs_bookings` DROP CONSTRAINT `bs_bookings_visibility_check`');
    }

    private function supported(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
