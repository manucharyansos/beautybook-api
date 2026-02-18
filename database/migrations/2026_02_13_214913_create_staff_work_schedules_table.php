<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_work_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('salon_id')->constrained()->cascadeOnDelete(); // important for multi-tenant
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday ... 6=Saturday
            $table->time('starts_at');
            $table->time('ends_at');

            $table->timestamps();

            $table->unique(['staff_id', 'day_of_week']);
            $table->index(['salon_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_work_schedules');
    }
};
