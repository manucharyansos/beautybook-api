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
                    // core
                    'blocks' => true,
                    // Phase 3A
                    'multi_service' => false,
                    // Phase 3B
                    'gift_cards' => false,
                    // Phase 3C
                    'loyalty' => false,
                    'export' => false,
                    'rooms' => false,
                    'online_booking' => false,

                    // Clinic roadmap (Phase 3D/3E)
                    'emr' => false,
                    'treatment_plans' => false,

                    // add-ons / upgrades
                    'sms_reminders' => 80,
                    'reminders' => false,
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

        // PRO — the most common for Armenia (up to ~5 staff for beauty)
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
                'seats' => 5,
                'staff_limit' => 5,
                'duration_days' => 30,
                'locations' => 1,
                'features' => [
                    'staff_limit' => 5,
                    'blocks' => true,
                    // Phase 3A
                    'multi_service' => true,
                    // Phase 3B
                    'gift_cards' => true,
                    // Phase 3C
                    'loyalty' => true,
                    'export' => true,
                    'rooms' => false,
                    'online_booking' => true,

                    // Clinic roadmap (Phase 3D/3E)
                    'emr' => false,
                    'treatment_plans' => false,
                    'sms_reminders' => 300,
                    'reminders' => false,
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

        // BUSINESS / CLINIC — bigger teams (clinic features + rooms)
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
                    'blocks' => true,
                    // Phase 3A
                    'multi_service' => true,
                    // Phase 3B
                    'gift_cards' => true,
                    // Phase 3C
                    'loyalty' => true,
                    'export' => true,
                    'rooms' => true,
                    'online_booking' => true,

                    // Clinic roadmap (Phase 3D/3E)
                    'emr' => true,
                    'treatment_plans' => true,
                    'sms_reminders' => 'unlimited',
                    'reminders' => true,
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
