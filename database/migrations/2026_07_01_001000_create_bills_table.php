<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('retailer_id');
            $table->date('date'); // billing date, covers txns up to this date since last bill
            $table->date('period_from')->nullable(); // date after previous bill (or retailer creation)

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('final_total', 14, 2)->default(0); // subtotal - commission
            $table->decimal('cash_paid', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0); // final_total - cash_paid (still owed)

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
