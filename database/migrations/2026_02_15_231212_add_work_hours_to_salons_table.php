<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->time('work_start')->default('10:00')->after('address');
            $table->time('work_end')->default('20:00')->after('work_start');
            $table->unsignedSmallInteger('slot_step_minutes')->default(15)->after('work_end');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn(['work_start','work_end','slot_step_minutes']);
        });
    }
};
