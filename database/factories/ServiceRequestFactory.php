<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['plomeria', 'electricidad', 'limpieza', 'jardineria', 'mudanza', 'reparacion']),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'budget_min' => fake()->randomFloat(2, 50, 500),
            'budget_max' => fake()->randomFloat(2, 100, 1000),
            'deadline' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'status' => 'open',
            'images' => [],
        ];
    }
}
