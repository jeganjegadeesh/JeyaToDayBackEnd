<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Drives the one-time "first login -> Company Setup -> Dashboard"
            // flow for a freshly-provisioned admin: false until the admin
            // saves the Company Setup form for the first time.
            $table->boolean('is_setup_complete')->default(false)->after('opening_balance_locked');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_setup_complete');
        });
    }
};