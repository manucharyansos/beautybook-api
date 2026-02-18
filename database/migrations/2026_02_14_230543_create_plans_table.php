<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');               // Starter
            $table->string('code')->unique();     // starter
            $table->unsignedInteger('price')->default(0); // AMD (կամ cents)
            $table->string('currency', 10)->default('AMD');
            $table->unsignedSmallInteger('seats')->default(5); // staff limit
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedSmallInteger('locations')->default(1); // future
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
