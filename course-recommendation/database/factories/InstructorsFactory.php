<?php

namespace Database\Factories;

use App\Models\Instructors;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class InstructorsFactory extends Factory
{
    protected $model = Instructors::class;

    public function definition()
    {
        return [
            'user_id' => User::factory()->create(['role' => 'instructor'])->id,
            'name' => $this->faker->name,
            'bio' => $this->faker->paragraph,
            'avatar' => $this->faker->imageUrl(),
            'organization' => $this->faker->company,
        ];
    }
}