<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('business_type')->default('beauty'); // beauty, dental
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_onboarding_completed')->default(false);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->enum('billing_status', ['active', 'suspended'])->default('active');
            $table->timestamp('suspended_at')->nullable();
            $table->time('work_start')->nullable();
            $table->time('work_end')->nullable();
            $table->unsignedSmallInteger('slot_step_minutes')->default(15);
            $table->string('timezone', 64)->default('Asia/Yerevan');
            $table->timestamps();

            // Indexes
            $table->index('business_type');
            $table->index('status');
        });
    }

    public function down(): void {
        Schema::dropIfExists('businesses');
    }
};
