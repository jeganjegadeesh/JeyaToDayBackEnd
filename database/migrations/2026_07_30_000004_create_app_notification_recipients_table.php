<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // One row per (notification, admin) pair. A notification is fanned out
    // to every admin of the company so each admin has an independent
    // read/unread state and app-icon badge count.
    public function up(): void
    {
        Schema::create('app_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_notification_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('app_notification_id', 'fk_notif_recipients_notification')
                ->references('id')->on('app_notifications')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_notif_recipients_user')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notification_recipients');
    }
};
