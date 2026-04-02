<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['retailer_id', 'date']);
        });

        // Add paid_amount and balance to bills table
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0.00)->after('final_amount');
            $table->decimal('balance_amount', 12, 2)->default(0.00)->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_payments');
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'balance_amount']);
        });
    }
};