<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Instructors;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Log as FacadesLog;

class DatabaseseederTest extends Seeder
{
    public function run(): void
    {
        $this->command->info('Running TestReviewMinimalSeeder...');

        // 1. Tạo 1 user (student)
        $user = User::create([
            'username' => 'test_student',
            'fullname' => 'Test Student',
            'email' => 'test_student@example.com',
            'password' => Hash::make('password'),
            'birthdate' => Carbon::now()->subYears(20),
            'gender' => 'Male',
            'role' => 'student',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Tạo 1 instructor
        $instructorUser = User::create([
            'username' => 'test_instructor',
            'fullname' => 'Test Instructor',
            'email' => 'test_instructor@example.com',
            'password' => Hash::make('password'),
            'birthdate' => Carbon::now()->subYears(30),
            'gender' => 'Female',
            'role' => 'instructor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $instructor = Instructors::create([
            'user_id' => $instructorUser->id,
            'bio' => 'Test instructor bio',
            'organization' => 'Test Academy',
            'email_paypal' => 'paypal_test@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Tạo 1 course
        $course = Course::create([
            'instructor_id' => $instructor->id,
            'course_name' => 'Test Course',
            'difficulty_level' => 'Beginner',
            'course_rating' => 0,
            'course_url' => 'http://example.com/test-course',
            'image' => 'http://example.com/test-image.jpg',
            'course_description' => 'This is a test course.',
            'price' => 100000,
            'skills' => 'Test Skill',
            'status' => 'approved',
            'is_certificate_enabled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Tạo 1 enrollment
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Tạo 1 review
        $review = Review::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'rating' => 4,
            'comment' => 'This is a test review.',
            'feedback_type' => 'content_quality',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        FacadesLog::info('Review:', [$review]);
        
        // Kiểm tra course_rating sau khi tạo review
        $updatedCourse = Course::find($course->id);
        $this->command->info("Course ID: {$course->id}, Course Name: {$course->course_name}, Course Rating: " . ($updatedCourse->course_rating ?? 'NULL'));

        $this->command->info('TestReviewMinimalSeeder completed!');
    }
}