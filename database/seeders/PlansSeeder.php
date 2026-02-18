<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'starter'],
            ['name' => 'Starter', 'price' => 0, 'currency' => 'AMD', 'seats' => 2, 'duration_days' => 30, 'is_active' => true]
        );

        Plan::updateOrCreate(
            ['code' => 'pro'],
            ['name' => 'Pro', 'price' => 12000, 'currency' => 'AMD', 'seats' => 5, 'duration_days' => 30, 'is_active' => true]
        );

        Plan::updateOrCreate(
            ['code' => 'business'],
            ['name' => 'Business', 'price' => 25000, 'currency' => 'AMD', 'seats' => 15, 'duration_days' => 30, 'is_active' => true]
        );
    }
}
