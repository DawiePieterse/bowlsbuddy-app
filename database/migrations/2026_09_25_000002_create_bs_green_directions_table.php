<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The direction of play the Club Secretary sets for a green: north-south or east-west. A row holds from its date
 * until the next row for that green, so the Secretary only sets it when it changes. New; the original app had no
 * direction of play.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_green_directions', function (Blueprint $table) {
            $table->increments('gdid');
            $table->string('green', 64)->comment('the part of the rink name before the dash, e.g. "A"');
            $table->date('date');
            $table->string('direction', 16)->comment('north-south|east-west');

            $table->unique(['green', 'date']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `bs_green_directions` ADD CONSTRAINT `bs_green_directions_direction_check` CHECK (`direction` IN ('north-south', 'east-west'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_green_directions');
    }
};
