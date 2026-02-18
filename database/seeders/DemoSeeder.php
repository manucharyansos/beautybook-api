<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Salon
        $salon = Salon::updateOrCreate(
            ['slug' => 'demo-salon'],
            [
                'name' => 'Demo Salon',
                'phone' => '+37400000000',
                'address' => 'Yerevan',
                'status' => 'active',
                // եթե ունես billing_status field՝
                'billing_status' => 'active',
                'work_start' => '10:00:00',
                'work_end' => '20:00:00',
                'slot_step_minutes' => 15,
            ]
        );

        // 2) Owner
        $owner = User::updateOrCreate(
            ['email' => 'owner@mail.com'],
            [
                'name' => 'Owner Name',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
                'salon_id' => $salon->id,
                'is_active' => true,
            ]
        );

        // 3) Manager + Staff
        $manager = User::updateOrCreate(
            ['email' => 'manager@mail.com'],
            [
                'name' => 'Manager Name',
                'password' => Hash::make('password'),
                'role' => User::ROLE_MANAGER,
                'salon_id' => $salon->id,
                'is_active' => true,
            ]
        );

        $staff1 = User::updateOrCreate(
            ['email' => 'staff1@mail.com'],
            [
                'name' => 'Staff One',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
                'salon_id' => $salon->id,
                'is_active' => true,
            ]
        );

        $staff2 = User::updateOrCreate(
            ['email' => 'staff2@mail.com'],
            [
                'name' => 'Staff Two',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
                'salon_id' => $salon->id,
                'is_active' => true,
            ]
        );

        // 4) Services
        $services = Service::factory()
            ->count(6)
            ->create(['salon_id' => $salon->id, 'currency' => 'AMD']);

        // 5) Subscription (attach plan)
        $plan = Plan::where('code', 'pro')->first();

        if ($plan) {
            Subscription::updateOrCreate(
                ['salon_id' => $salon->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'trial_ends_at' => now()->subDays(1), // trial անցած
                    'current_period_ends_at' => now()->addDays(30),
                ]
            );
        }

        // 6) Bookings (last 14 days + next 7 days)
        $staffIds = [$owner->id, $manager->id, $staff1->id, $staff2->id];

        // done bookings (revenue)
        for ($d = 1; $d <= 14; $d++) {
            $day = Carbon::now()->subDays($d)->setTime(10, 0);

            foreach (range(1, 2) as $i) {
                $service = $services->random();
                $start = (clone $day)->addHours($i * 2);
                $end = (clone $start)->addMinutes((int)$service->duration_minutes);

                Booking::create([
                    'salon_id' => $salon->id,
                    'service_id' => $service->id,
                    'staff_id' => $staffIds[array_rand($staffIds)],
                    'client_name' => 'Client '.Str::upper(Str::random(4)),
                    'client_phone' => '+374'.random_int(10000000, 99999999),
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'status' => 'done',
                    'notes' => null,
                    'final_price' => (int)($service->price ?? 0),
                    'currency' => $service->currency ?? 'AMD',
                ]);
            }
        }

        // upcoming bookings (confirmed)
        for ($d = 0; $d <= 7; $d++) {
            $day = Carbon::now()->addDays($d)->setTime(11, 0);

            $service = $services->random();
            $start = (clone $day)->addHours(random_int(0, 5));
            $end = (clone $start)->addMinutes((int)$service->duration_minutes);

            Booking::create([
                'salon_id' => $salon->id,
                'service_id' => $service->id,
                'staff_id' => $staffIds[array_rand($staffIds)],
                'client_name' => 'Upcoming '.Str::upper(Str::random(4)),
                'client_phone' => '+374'.random_int(10000000, 99999999),
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => 'confirmed',
                'notes' => null,
                'final_price' => (int)($service->price ?? 0),
                'currency' => $service->currency ?? 'AMD',
            ]);
        }
    }
}
