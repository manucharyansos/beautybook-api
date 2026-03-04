<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Avoid MySQL enum truncation issues across environments.
        // We standardize to VARCHAR(20).
        DB::statement("ALTER TABLE bookings MODIFY status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // We keep it as string to avoid destructive enum downgrade.
    }
};
