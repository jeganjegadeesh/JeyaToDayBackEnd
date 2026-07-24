<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailer_loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('retailer_id');
            $table->decimal('amount', 14, 2);
            $table->date('date');
            $table->string('remarks')->nullable();
            // future enhancement placeholder for loan repayment tracking
            $table->decimal('repaid_amount', 14, 2)->default(0);

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
        Schema::dropIfExists('retailer_loans');
    }
};
