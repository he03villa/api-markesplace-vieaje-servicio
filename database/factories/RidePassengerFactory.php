<?php

namespace Database\Factories;

use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RidePassengerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ride_request_id' => RideRequest::factory(),
            'user_id' => User::factory(),
            'seats_reserved' => 1,
            'status' => 'confirmed',
            'price_paid' => fake()->randomFloat(2, 5, 50),
            'price_per_seat' => fake()->randomFloat(2, 5, 50),
        ];
    }
}
