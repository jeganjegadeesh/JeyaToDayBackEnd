<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retailers', function (Blueprint $table) {
            $table->string('profile_image')->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('retailers', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });
    }
};