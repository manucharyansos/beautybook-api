<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize any legacy values first
        DB::statement("UPDATE `bookings` SET `status`='done' WHERE `status` IN ('completed','complete')");

        // Enum truncation issues differ across environments. For stability we store status as VARCHAR.
        DB::statement("ALTER TABLE `bookings` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Restore enum (best-effort). Keep the superset you use in the app.
        DB::statement("ALTER TABLE `bookings` MODIFY `status` ENUM('pending','confirmed','done','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
