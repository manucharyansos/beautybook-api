<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedInteger('price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['salon_id','is_active']);
        });
    }
    public function down(): void { Schema::dropIfExists('services'); }
};
