<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * Armenia-focused pricing (AMD) + conditions for quick realistic testing.
         *
         * Notes:
         * - We keep legacy price_beauty / price_dental for now.
         *   Interpreted as:
         *      price_beauty  => salon monthly price (AMD)
         *      price_dental  => clinic monthly price (AMD)
         * - api_access is OFF everywhere (per decision: no API access now).
         */

        // STARTER — micro teams, cheap entry
        Plan::updateOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'code' => 'starter',
                'version' => 1,
                // legacy column kept, but new filtering uses allowed_business_types
                'business_type' => null,
                'allowed_business_types' => ['salon', 'clinic'],
                'description' => 'Փոքր թիմերի համար (սկսելու լավագույնը)',
                'price_beauty' => 5900,
                'price_dental' => 7900,
                'currency' => 'AMD',
                'seats' => 2,
                'staff_limit' => 2,
                'duration_days' => 30,
                'locations' => 1,
                'features' => [
                    'staff_limit' => 2,
                    'sms_reminders' => 80,
                    'analytics' => false,
                    'clinic_patient_card' => false,
                    'api_access' => false,
                    'priority_support' => false,
                    'dedicated_manager' => false,
                ],
                'sort_order' => 1,
                'is_active' => true,
                'is_visible' => true,
            ]
        );

        // PRO — the most common for Armenia (5–7 staff)
        Plan::updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro',
                'code' => 'pro',
                'version' => 1,
                'business_type' => null,
                'allowed_business_types' => ['salon', 'clinic'],
                'description' => 'Աճող թիմերի համար (ամենապահանջվածը)',
                'price_beauty' => 12900,
                'price_dental' => 15900,
                'currency' => 'AMD',
                'seats' => 6,
                'staff_limit' => 6,
                'duration_days' => 30,
                'locations' => 1,
                'features' => [
                    'staff_limit' => 6,
                    'sms_reminders' => 300,
                    'analytics' => true,
                    'clinic_patient_card' => true,
                    'api_access' => false,
                    'priority_support' => false,
                    'dedicated_manager' => false,
                ],
                'sort_order' => 2,
                'is_active' => true,
                'is_visible' => true,
            ]
        );

        // BUSINESS — multi-staff + multi-location
        Plan::updateOrCreate(
            ['code' => 'business'],
            [
                'name' => 'Business',
                'code' => 'business',
                'version' => 1,
                'business_type' => null,
                'allowed_business_types' => ['salon', 'clinic'],
                'description' => 'Ավելի մեծ թիմերի և 2-3 մասնաճյուղի համար',
                'price_beauty' => 24900,
                'price_dental' => 32900,
                'currency' => 'AMD',
                'seats' => 15,
                'staff_limit' => 15,
                'duration_days' => 30,
                'locations' => 3,
                'features' => [
                    'staff_limit' => 15,
                    'sms_reminders' => 'unlimited',
                    'analytics' => true,
                    'clinic_patient_card' => true,
                    'api_access' => false,
                    'priority_support' => true,
                    'dedicated_manager' => false,
                ],
                'sort_order' => 3,
                'is_active' => true,
                'is_visible' => true,
            ]
        );
    }
}
