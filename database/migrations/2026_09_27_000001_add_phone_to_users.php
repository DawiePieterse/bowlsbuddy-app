<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members register and log in with their cellphone (WhatsApp) number instead of an email address.
 * The number is stored normalised (+27...); email stays for staff accounts and older members.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('bs_users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }
};
