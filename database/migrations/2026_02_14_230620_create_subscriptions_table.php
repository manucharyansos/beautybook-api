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
        Schema::create('subscriptions', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            $table->string('status')->default('trialing'); // trialing, active, past_due, canceled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            // payment integration-ի համար պահելու տեղ (հետագայում)
            $table->string('provider')->nullable();        // stripe, arca, idram...
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();

            $table->timestamps();

            $table->index(['salon_id','status']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
