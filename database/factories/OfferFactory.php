<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'user_id' => User::factory(),
            'price' => fake()->randomFloat(2, 50, 500),
            'description' => fake()->paragraph(),
            'estimated_time' => fake()->randomElement(['1-2 horas', '3-4 horas', '1 dia', '2-3 dias']),
            'status' => 'pending',
        ];
    }
}
