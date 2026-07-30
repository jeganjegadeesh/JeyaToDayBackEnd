<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Named "app_notifications" (not "notifications") to avoid clashing with
    // Laravel's own built-in notifications table/convention.
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            // 'password_reset_request' | 'new_bill'
            $table->string('type');
            $table->string('title');
            $table->text('body');
            // Deep-link payload for the mobile app, e.g.
            // {"screen":"bill_detail","bill_id":"12"}
            $table->json('data')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
