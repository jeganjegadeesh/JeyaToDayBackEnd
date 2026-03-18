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
            $table->foreignId('retailer_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->decimal('total_sales', 12, 2)->default(0.00);
            $table->decimal('commission', 12, 2)->default(0.00);
            $table->decimal('final_amount', 12, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['retailer_id', 'date']);
            $table->index('date');
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('given_qty')->default(0);
            $table->integer('returned_qty')->default(0);
            $table->integer('sold_qty')->default(0);
            $table->decimal('price', 10, 2);
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};