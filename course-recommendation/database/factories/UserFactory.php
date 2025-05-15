<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'userid_DI' => $this->faker->unique()->uuid,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'final_cc_cname_DI' => 'Unknown',
            'LoE_DI' => 'Unknown',
            'YoB' => $this->faker->year,
            'gender' => $this->faker->randomElement(['male', 'female', null]),
            'role' => 'student',
            'provider' => null,
            'provider_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}