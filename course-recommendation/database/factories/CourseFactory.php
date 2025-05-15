<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition()
    {
        return [
            'course_name' => $this->faker->sentence,
            'university' => $this->faker->company,
            'difficulty_level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'course_rating' => $this->faker->randomFloat(1, 0, 5),
            'course_url' => $this->faker->url,
            'course_description' => $this->faker->paragraph,
            'price' => $this->faker->numberBetween(0, 1000000),
            'skills' => $this->faker->words(3, true),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}