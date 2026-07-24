<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable(); // nullable only for the very first bootstrap admin
            $table->string('name');
            $table->string('phone_number')->unique();
            $table->string('password'); // default hashed '123456' on creation
            // Roles: admin, manager, retailer
            $table->enum('type', ['admin', 'manager', 'retailer'])->default('manager');
            $table->decimal('commission', 5, 2)->nullable(); // percentage, only relevant when type = retailer
            $table->enum('theme', ['light', 'dark'])->default('light');
            $table->enum('language', ['ta', 'en'])->default('ta');
            $table->enum('font_size', ['S', 'M', 'L', 'XL'])->default('M');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
