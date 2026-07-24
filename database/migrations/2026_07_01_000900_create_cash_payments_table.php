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
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('retailer_id');
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_billed')->default(0); // consumed into a bill's "Cash Paid" figure
            $table->unsignedBigInteger('bill_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
            $table->index(['retailer_id', 'is_billed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_payments');
    }
};
