<?php

namespace Database\Factories;

use App\Models\Admins;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminsFactory extends Factory
{
    protected $model = Admins::class;

    public function definition()
    {
        return [
            'user_id' => User::factory()->create(['role' => 'admin'])->id,
            'admin_level' => $this->faker->randomElement(['organization', 'program']),
            'activity_log' => $this->faker->paragraph,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}