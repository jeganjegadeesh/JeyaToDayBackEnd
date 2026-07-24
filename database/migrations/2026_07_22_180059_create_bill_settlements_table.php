<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks cash collected from a retailer against a specific bill's
     * outstanding grand total (the post-generation "did they pay?"
     * confirmation, or a later manual settlement).
     *
     * Deliberately separate from `cash_payments`: cash_payments is the
     * retailer's own advance/ad-hoc payments (consumed automatically into
     * the NEXT bill's "Cash Paid" figure). A bill_settlement instead closes
     * out an ALREADY generated bill's remaining balance directly - it must
     * not also appear as an unbilled cash_payment or it would be double
     * counted / re-applied to a future bill.
     */
    public function up(): void
    {
        Schema::create('bill_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('retailer_id');
            $table->date('date');
            $table->decimal('amount', 14, 2);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('bill_id')->references('id')->on('bills')->cascadeOnDelete();
            $table->foreign('retailer_id')->references('id')->on('retailers')->cascadeOnDelete();
            $table->index(['bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_settlements');
    }
};