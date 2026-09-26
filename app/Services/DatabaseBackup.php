<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * A plain-PHP SQL dump for the admin panel's Download backup button (PLAN.md Phase 5): shared
 * hosts give us no mysqldump, so the dump is built from SHOW CREATE TABLE and the rows. Restoring
 * is an import in phpMyAdmin, as with install.sql.
 */
class DatabaseBackup
{
    /**
     * Streams the whole database as SQL, one chunk per call to $write.
     *
     * @param  callable(string): void  $write
     */
    public function dump(callable $write): void
    {
        $database = DB::getDatabaseName();

        $write("-- Bowls Buddy backup of `{$database}`\n-- ".now()->format('Y-m-d H:i:s').' ('.config('app.timezone').")\n\n");
        $write("SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $table = (string) current((array) $row);

            $create = (array) DB::select("SHOW CREATE TABLE `{$table}`")[0];

            $write("DROP TABLE IF EXISTS `{$table}`;\n");
            $write($create['Create Table'].";\n\n");

            DB::table($table)->orderBy(DB::raw('1'))->chunk(200, function ($rows) use ($table, $write) {
                $values = [];

                foreach ($rows as $row) {
                    $fields = array_map(
                        fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value),
                        (array) $row,
                    );

                    $values[] = '('.implode(',', $fields).')';
                }

                if ($values) {
                    $write("INSERT INTO `{$table}` VALUES\n".implode(",\n", $values).";\n");
                }
            });

            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    }
}
