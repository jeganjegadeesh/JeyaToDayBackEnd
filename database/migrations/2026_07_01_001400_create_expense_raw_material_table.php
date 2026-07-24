<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_raw_material', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('raw_material_id');
            $table->timestamps();

            $table->foreign('expense_id')->references('id')->on('expenses')->cascadeOnDelete();
            $table->foreign('raw_material_id')->references('id')->on('raw_materials')->cascadeOnDelete();
            $table->unique(['expense_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_raw_material');
    }
};
