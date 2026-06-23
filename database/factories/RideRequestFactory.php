<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RideRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'driver_id' => User::factory(),
            'origin_address' => fake()->address(),
            'origin_lat' => fake()->latitude(),
            'origin_lng' => fake()->longitude(),
            'destination_address' => fake()->address(),
            'destination_lat' => fake()->latitude(),
            'destination_lng' => fake()->longitude(),
            'departure_time' => fake()->dateTimeBetween('+1 hour', '+1 week'),
            'available_seats' => fake()->numberBetween(1, 4),
            'total_seats' => fake()->numberBetween(1, 4),
            'price_per_seat' => fake()->randomFloat(2, 5, 50),
            'status' => 'available',
            'vehicle_make' => fake()->randomElement(['Toyota', 'Honda', 'Nissan', 'Chevrolet', 'Ford']),
            'vehicle_model' => fake()->word(),
            'vehicle_year' => fake()->year(),
            'vehicle_color' => fake()->safeColorName(),
        ];
    }
}
