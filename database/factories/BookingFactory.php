<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = now()->addDays(rand(0, 10))->setTime(rand(9, 18), 0);

        return [
            'salon_id' => Salon::factory(),
            'service_id' => Service::factory(),
            'staff_id' => null,
            'client_name' => $this->faker->name(),
            'client_phone' => $this->faker->e164PhoneNumber(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addMinutes(60),
            'status' => 'pending',
            'notes' => null,
        ];
    }
}
