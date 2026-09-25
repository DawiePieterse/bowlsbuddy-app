<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Members log in with their mobile number instead of their email address, and new accounts wait for the Club
 * Secretary to activate them. bs_users.phone is new (the original kept a phone number in bs_users_meta, which
 * can't be unique or looked up quickly); it holds the international form, e.g. +27821234567.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->unique()->after('email');
        });

        DB::table('bs_options')->where('key', 'service.user.activation')->update(['value' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('bs_users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });

        DB::table('bs_options')->where('key', 'service.user.activation')->update(['value' => 'immediate']);
    }
};
