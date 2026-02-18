<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('client_name');
            $table->string('client_phone');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->enum('status', ['pending','confirmed','cancelled','done'])->default('pending');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['salon_id','starts_at']);
            $table->index(['salon_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('bookings'); }
};
