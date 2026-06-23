<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reviewer_id' => User::factory(),
            'reviewed_user_id' => User::factory(),
            'reviewable_type' => 'App\Models\ServiceRequest',
            'reviewable_id' => 1,
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(),
        ];
    }
}
