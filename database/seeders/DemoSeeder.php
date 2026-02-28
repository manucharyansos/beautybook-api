<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Service;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // =========================
        // 1) Businesses (different plans)
        // =========================

        $starterSalon = Business::updateOrCreate(
            ['slug' => 'starter-salon-yerevan'],
            [
                'name' => 'Starter Salon Yerevan',
                'business_type' => 'salon',
                'phone' => '+37491000001',
                'address' => 'Yerevan, Armenia',
                'status' => 'active',
                'work_start' => '10:00:00',
                'work_end' => '20:00:00',
                'slot_step_minutes' => 15,
                'timezone' => 'Asia/Yerevan',
                'is_onboarding_completed' => true,
            ]
        );

        $proSalon = Business::updateOrCreate(
            ['slug' => 'pro-salon-yerevan'],
            [
                'name' => 'Pro Salon Yerevan',
                'business_type' => 'salon',
                'phone' => '+37491000003',
                'address' => 'Yerevan, Armenia',
                'status' => 'active',
                'work_start' => '10:00:00',
                'work_end' => '20:00:00',
                'slot_step_minutes' => 15,
                'timezone' => 'Asia/Yerevan',
                'is_onboarding_completed' => true,
            ]
        );

        $proClinic = Business::updateOrCreate(
            ['slug' => 'pro-clinic-yerevan'],
            [
                'name' => 'Pro Clinic Yerevan',
                'business_type' => 'clinic',
                'phone' => '+37491000004',
                'address' => 'Yerevan, Armenia',
                'status' => 'active',
                'work_start' => '09:00:00',
                'work_end' => '18:00:00',
                'slot_step_minutes' => 30,
                'timezone' => 'Asia/Yerevan',
                'is_onboarding_completed' => true,
            ]
        );

        $businessClinic = Business::updateOrCreate(
            ['slug' => 'business-clinic-yerevan'],
            [
                'name' => 'Business Clinic Yerevan',
                'business_type' => 'clinic',
                'phone' => '+37491000002',
                'address' => 'Yerevan, Armenia',
                'status' => 'active',
                'work_start' => '09:00:00',
                'work_end' => '18:00:00',
                'slot_step_minutes' => 30,
                'timezone' => 'Asia/Yerevan',
                'is_onboarding_completed' => true,
            ]
        );

        // =========================
        // 2) Users
        // =========================
        $this->createBusinessUsers($starterSalon, 'starter_salon', false);
        $this->createBusinessUsers($proSalon, 'pro_salon', false);

        $this->createBusinessUsers($proClinic, 'pro_clinic', true);
        $this->createBusinessUsers($businessClinic, 'biz_clinic', true);

        // =========================
        // 3) Services
        // =========================
        $starterSalonServices = $this->createBeautyServices($starterSalon);
        $proSalonServices = $this->createBeautyServices($proSalon);

        $proClinicServices = $this->createDentalServices($proClinic);
        $businessClinicServices = $this->createDentalServices($businessClinic);

        // =========================
        // 4) Rooms (clinic only)
        // =========================
        $proClinicRooms = $this->createDentalRooms($proClinic);
        $businessClinicRooms = $this->createDentalRooms($businessClinic);

        // =========================
        // 5) Clients
        // =========================
        $this->createClients($starterSalon, 14);
        $this->createClients($proSalon, 18);

        $this->createClients($proClinic, 14, true);
        $this->createClients($businessClinic, 18, true);

        // =========================
        // 6) Bookings
        // =========================
        $this->createBeautyBookings($starterSalon, $starterSalonServices);
        $this->createBeautyBookings($proSalon, $proSalonServices);

        $this->createDentalBookings($proClinic, $proClinicServices, $proClinicRooms);
        $this->createDentalBookings($businessClinic, $businessClinicServices, $businessClinicRooms);

        // =========================
        // 7) Subscriptions (different plans)
        // =========================
        $this->createSubscriptions($starterSalon, 'starter');
        $this->createSubscriptions($proSalon, 'pro');
        $this->createSubscriptions($proClinic, 'pro');
        $this->createSubscriptions($businessClinic, 'business');

        $this->command?->info('✅ Demo data seeded successfully!');
        $this->command?->info('📧 starter_salon.owner@mail.com / password');
        $this->command?->info('📧 pro_salon.owner@mail.com / password');
        $this->command?->info('📧 pro_clinic.owner@mail.com / password');
        $this->command?->info('📧 biz_clinic.owner@mail.com / password');
    }

    private function createBusinessUsers(Business $business, string $prefix, bool $isClinic = false): void
    {
        // Owner
        User::updateOrCreate(
            ['email' => $prefix . '.owner@mail.com'],
            [
                'name' => $isClinic ? 'Դոկտոր Արմեն Պետրոսյան' : 'Աննա Մարտիրոսյան',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
                'business_id' => $business->id,
                'is_active' => true,
            ]
        );

        // Manager
        User::updateOrCreate(
            ['email' => $prefix . '.manager@mail.com'],
            [
                'name' => $isClinic ? 'Անի Հակոբյան' : 'Արմեն Գրիգորյան',
                'password' => Hash::make('password'),
                'role' => User::ROLE_MANAGER,
                'business_id' => $business->id,
                'is_active' => true,
            ]
        );

        // Staff 1
        User::updateOrCreate(
            ['email' => $prefix . '.staff1@mail.com'],
            [
                'name' => $isClinic ? 'Մարիամ Սարգսյան' : 'Մարիամ Հակոբյան',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
                'business_id' => $business->id,
                'is_active' => true,
            ]
        );

        // Staff 2
        User::updateOrCreate(
            ['email' => $prefix . '.staff2@mail.com'],
            [
                'name' => $isClinic ? 'Դավիթ Ղազարյան' : 'Լիլիթ Սարգսյան',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
                'business_id' => $business->id,
                'is_active' => true,
            ]
        );
    }

    private function createBeautyServices(Business $business): array
    {
        $services = [
            ['name' => 'Կանացի սանրվածք', 'duration' => 60, 'price' => 7000],
            ['name' => 'Տղամարդու սանրվածք', 'duration' => 30, 'price' => 4000],
            ['name' => 'Մատնահարդարում (գել)', 'duration' => 75, 'price' => 8000],
            ['name' => 'Ոտնահարդարում', 'duration' => 75, 'price' => 10000],
            ['name' => 'Դիմահարդարում', 'duration' => 90, 'price' => 15000],
            ['name' => 'Հոնքերի շտկում', 'duration' => 30, 'price' => 3500],
            ['name' => 'Էպիլյացիա (մոմ)', 'duration' => 45, 'price' => 6000],
            ['name' => 'Թարթիչների լամինացիա', 'duration' => 60, 'price' => 12000],
        ];

        $created = [];
        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'name' => $serviceData['name'],
                ],
                [
                    'duration_minutes' => $serviceData['duration'],
                    'price' => $serviceData['price'],
                    'currency' => 'AMD',
                    'is_active' => true,
                ]
            );
            $created[] = $service;
        }

        return $created;
    }

    private function createDentalServices(Business $business): array
    {
        $services = [
            ['name' => 'Խորհրդատվություն', 'duration' => 20, 'price' => 5000],
            ['name' => 'Ատամների պրոֆ․ մաքրում', 'duration' => 45, 'price' => 18000],
            ['name' => 'Պլոմբավորում', 'duration' => 60, 'price' => 25000],
            ['name' => 'Ատամի հեռացում', 'duration' => 40, 'price' => 25000],
            ['name' => 'Արմատային ջրանցք', 'duration' => 120, 'price' => 65000],
            ['name' => 'Պսակավորում', 'duration' => 90, 'price' => 95000],
            ['name' => 'Ատամների սպիտակեցում', 'duration' => 75, 'price' => 60000],
        ];

        $created = [];
        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'name' => $serviceData['name'],
                ],
                [
                    'duration_minutes' => $serviceData['duration'],
                    'price' => $serviceData['price'],
                    'currency' => 'AMD',
                    'is_active' => true,
                ]
            );
            $created[] = $service;
        }

        return $created;
    }

    private function createDentalRooms(Business $business): array
    {
        $rooms = [
            ['name' => 'Սենյակ 1', 'type' => 'room', 'capacity' => 1],
            ['name' => 'Սենյակ 2', 'type' => 'room', 'capacity' => 1],
            ['name' => 'Աթոռ 1', 'type' => 'chair', 'capacity' => 1],
            ['name' => 'Աթոռ 2', 'type' => 'chair', 'capacity' => 1],
            ['name' => 'Վիրահատարան', 'type' => 'surgery', 'capacity' => 2],
        ];

        $created = [];
        foreach ($rooms as $roomData) {
            $room = Room::updateOrCreate(
                [
                    'business_id' => $business->id,
                    'name' => $roomData['name'],
                ],
                [
                    'type' => $roomData['type'],
                    'capacity' => $roomData['capacity'],
                    'equipment' => ['X-Ray', 'Surgical Light'],
                    'is_active' => true,
                ]
            );
            $created[] = $room;
        }

        return $created;
    }

    private function createClients(Business $business, int $count, bool $isClinic = false): void
    {
        $firstNames = ['Աննա', 'Մարիամ', 'Լիլիթ', 'Աստղիկ', 'Նարե', 'Սոնա', 'Գայանե', 'Արմեն', 'Դավիթ', 'Արամ'];
        $lastNames = ['Մարտիրոսյան', 'Հակոբյան', 'Սարգսյան', 'Գրիգորյան', 'Հարությունյան'];

        for ($i = 0; $i < $count; $i++) {
            Client::create([
                'business_id' => $business->id,
                'name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
                'phone' => '+374' . random_int(77000000, 77999999),
                'email' => 'client' . $business->id . '_' . $i . '@mail.com',
                'birth_date' => $isClinic ? now()->subYears(rand(20, 60))->subDays(rand(0, 365)) : null,
                'blood_type' => $isClinic ? (rand(0, 4) ? ['A+','A-','B+','B-','AB+','AB-','O+','O-'][rand(0,7)] : null) : null,
            ]);
        }
    }

    private function createBeautyBookings(Business $business, array $services): void
    {
        $staffIds = $business->users()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_MANAGER])
            ->pluck('id')
            ->toArray();

        $clientIds = $business->clients()->pluck('id')->toArray();
        if (!$staffIds || !$clientIds || !$services) return;

        // Past bookings (done)
        for ($d = 1; $d <= 30; $d++) {
            $day = Carbon::now()->subDays($d)->setTime(10, 0);
            $this->createBookingsForDay($business, $day, $services, $staffIds, $clientIds, rand(2, 5), 'confirmed');
        }

        // Future bookings (confirmed)
        for ($d = 0; $d <= 7; $d++) {
            $day = Carbon::now()->addDays($d)->setTime(10, 0);
            $this->createBookingsForDay($business, $day, $services, $staffIds, $clientIds, rand(1, 3), 'confirmed');
        }
    }

    private function createDentalBookings(Business $business, array $services, array $rooms): void
    {
        $staffIds = $business->users()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_MANAGER])
            ->pluck('id')
            ->toArray();

        $clientIds = $business->clients()->pluck('id')->toArray();
        $roomIds = collect($rooms)->pluck('id')->toArray();

        if (!$staffIds || !$clientIds || !$services || !$roomIds) return;

        for ($d = 1; $d <= 30; $d++) {
            $day = Carbon::now()->subDays($d)->setTime(9, 0);
            $this->createDentalBookingsForDay(
                $business,
                $day,
                $services,
                $staffIds,
                $clientIds,
                $roomIds,
                rand(2, 4),
                'confirmed'
            );
        }

        // Future bookings (confirmed)
        for ($d = 0; $d <= 7; $d++) {
            $day = Carbon::now()->addDays($d)->setTime(9, 0);
            $this->createDentalBookingsForDay(
                $business,
                $day,
                $services,
                $staffIds,
                $clientIds,
                $roomIds,
                rand(1, 3),
                'confirmed'
            );
        }
    }

    private function createBookingsForDay(Business $business, Carbon $day, array $services, array $staffIds, array $clientIds, int $count, string $status): void
    {
        for ($i = 0; $i < $count; $i++) {
            $service = $services[array_rand($services)];
            $hour = rand(10, 18);
            $minute = rand(0, 3) * 15;

            $start = (clone $day)->setTime($hour, $minute);
            $end = (clone $start)->addMinutes((int)$service->duration_minutes);

            if ($end->hour >= 20) continue;

            $clientId = $clientIds[array_rand($clientIds)];
            $client = Client::find($clientId);
            if (!$client) continue;

            Booking::create([
                'business_id' => $business->id,
                'service_id' => $service->id,
                'staff_id' => $staffIds[array_rand($staffIds)],
                'client_id' => $clientId,
                'client_name' => $client->name,
                'client_phone' => $client->phone,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => $status,
                'final_price' => $service->price,
                'currency' => 'AMD',
            ]);
        }
    }

    private function createDentalBookingsForDay(Business $business, Carbon $day, array $services, array $staffIds, array $clientIds, array $roomIds, int $count, string $status): void
    {
        for ($i = 0; $i < $count; $i++) {
            $service = $services[array_rand($services)];
            $hour = rand(9, 16);
            $minute = rand(0, 1) * 30;

            $start = (clone $day)->setTime($hour, $minute);
            $end = (clone $start)->addMinutes((int)$service->duration_minutes);

            if ($end->hour >= 18) continue;

            $clientId = $clientIds[array_rand($clientIds)];
            $client = Client::find($clientId);
            if (!$client) continue;

            Booking::create([
                'business_id' => $business->id,
                'service_id' => $service->id,
                'staff_id' => $staffIds[array_rand($staffIds)],
                'client_id' => $clientId,
                'client_name' => $client->name,
                'client_phone' => $client->phone,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => $status,
                'final_price' => $service->price,
                'currency' => 'AMD',
                'room_id' => $roomIds[array_rand($roomIds)],
                'clinical_notes' => 'Ամեն ինչ նորմալ է',
                'is_emergency' => rand(0, 10) === 0,
            ]);
        }
    }

    private function createSubscriptions(Business $business, string $planCode = 'pro'): void
    {
        $plan = Plan::where('code', $planCode)->first();
        if (!$plan) return;

        $sub = Subscription::updateOrCreate(
            ['business_id' => $business->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => now()->addDays(7),
                'current_period_starts_at' => now()->startOfDay(),
                'current_period_ends_at' => now()->addDays(30),
            ]
        );

        if (method_exists($sub, 'applyPlanSnapshot')) {
            $sub->applyPlanSnapshot($plan);
            $sub->save();
        }
    }
}
