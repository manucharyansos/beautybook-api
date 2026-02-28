<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Normalize existing values (legacy -> new)
        DB::table('businesses')->where('business_type', 'beauty')->update(['business_type' => 'salon']);
        DB::table('businesses')->where('business_type', 'dental')->update(['business_type' => 'clinic']);
    }

    public function down(): void
    {
        DB::table('businesses')->where('business_type', 'salon')->update(['business_type' => 'beauty']);
        DB::table('businesses')->where('business_type', 'clinic')->update(['business_type' => 'dental']);
    }
};
