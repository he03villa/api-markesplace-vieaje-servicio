<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_a_id' => User::factory(),
            'user_b_id' => User::factory(),
            'unread_a' => 0,
            'unread_b' => 0,
        ];
    }
}
