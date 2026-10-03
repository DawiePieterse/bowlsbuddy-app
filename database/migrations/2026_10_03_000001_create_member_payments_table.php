<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membership fees the Secretary records by hand (cash, EFT...): the club's payment history per member. Not
 * online payments, which stay out of scope (PLAN.md section 3). A "bb_" table, as it isn't one of the original
 * bs_* tables; payments go with the member when their account is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bb_member_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('uid');
            $table->date('paid_on');
            $table->unsignedSmallInteger('year')->comment('The membership year it pays for, by the calendar year it starts in');
            $table->decimal('amount', 10, 2);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('uid')->references('uid')->on('bs_users')->cascadeOnDelete();
            $table->index(['year', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bb_member_payments');
    }
};
