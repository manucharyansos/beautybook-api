<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Haircut','Coloring','Manicure','Pedicure','Makeup','Massage']),
            'duration_minutes' => $this->faker->randomElement([30,45,60,90]),
            'price' => $this->faker->numberBetween(3000, 25000),
            'currency' => 'AMD',
            'is_active' => true,
        ];
    }
}
