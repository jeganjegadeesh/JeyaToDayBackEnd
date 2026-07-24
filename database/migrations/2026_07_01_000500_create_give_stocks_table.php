<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('give_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('retailer_id');
            $table->date('date');
            // set true once this batch has been included in a generated bill
            $table->boolean('is_billed')->default(0);
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
        Schema::dropIfExists('give_stocks');
    }
};
