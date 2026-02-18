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
        Schema::table('salons', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->enum('billing_status', ['active','suspended'])->default('active')->after('status');
            $table->timestamp('suspended_at')->nullable()->after('billing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            //
        });
    }
};
