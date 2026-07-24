<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // The single date the opening_balance amount applies to (e.g. 1 July).
            // Cash reports only "inject" opening_balance on this exact date; every
            // day after that carries forward the previous day's closing balance.
            $table->date('opening_balance_date')->nullable()->after('opening_balance');

            // Once true, opening_balance / opening_balance_date can no longer be
            // edited via the API. This flips to true automatically the moment the
            // first expense or retailer loan is recorded, so historic cash reports
            // can never be silently rewritten.
            $table->boolean('opening_balance_locked')->default(false)->after('opening_balance_date');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['opening_balance_date', 'opening_balance_locked']);
        });
    }
};
