<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('given_qty', 10, 2)->default(0);
            $table->decimal('returned_qty', 10, 2)->default(0);
            $table->decimal('sold_qty', 10, 2)->default(0); // given - returned
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0); // sold_qty * rate
            $table->timestamps();

            $table->foreign('bill_id')->references('id')->on('bills')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
