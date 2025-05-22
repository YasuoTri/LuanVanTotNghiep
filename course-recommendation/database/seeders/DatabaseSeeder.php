<?php

namespace Database\Seeders;

use App\Models\Course_Instructors;
use App\Models\Instructors;
use App\Models\User;
use App\Models\Category;
use App\Models\Student;
use App\Models\Admin;
use App\Models\Admins;
use App\Models\Instructor;
use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\Interaction;
use App\Models\Enrollment;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Review;
use App\Models\ForumPost;
use App\Models\SimilarityMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Users - Create 50 diverse users
        $this->seedUsers();
        
        // Seed Categories
        $this->seedCategories();
        
        // Seed Courses - Create 100 courses
        $this->seedCourses();
        
        // Seed Students
        $this->seedStudents();
        
        // Seed Admins
        $this->seedAdmins();
        
        // Seed Instructors
        $this->seedInstructors();
        
        // Seed Course Instructors
        $this->seedCourseInstructors();
        
        // Seed Interactions
        $this->seedInteractions();
        
        // Seed Enrollments
        $this->seedEnrollments();
        
        // Seed Certificates
        $this->seedCertificates();
        
        // Seed Coupons
        $this->seedCoupons();
        
        // Seed Payments
        $this->seedPayments();
        
        // Seed Lessons
        $this->seedLessons();
        
        // Seed Lesson Progress
        $this->seedLessonProgress();
        
        // Seed Quizzes
        $this->seedQuizzes();
        
        // Seed Quiz Results
        $this->seedQuizResults();
        
        // Seed Reviews
        $this->seedReviews();
        
        // Seed Forum Posts
        $this->seedForumPosts();
        
        // Seed Similarity Matrix
        $this->seedSimilarityMatrix();
    }

    private function seedUsers()
    {
        $this->command->info('Seeding 50 users...');
        
        $educationLevels = ['High School', 'Bachelor', 'Master', 'PhD', 'Associate', 'Unknown'];
        $countries = [
            'United States', 'India', 'United Kingdom', 'Canada', 'Australia', 
            'Germany', 'France', 'Brazil', 'Japan', 'China', 'Vietnam', 'Unknown'
        ];
        $genders = ['Male', 'Female', 'Other', 'Prefer not to say', null];
        
        // Create 45 student users
        for ($i = 0; $i < 45; $i++) {
            $birthYear = rand(1980, 2005);
            
            User::create([
                'userid_DI' => 'user_' . Str::uuid(),
                'username' => 'user_' . ($i + 1),
                'email' => 'user' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'final_cc_cname_DI' => $countries[array_rand($countries)],
                'LoE_DI' => $educationLevels[array_rand($educationLevels)],
                'YoB' => $birthYear,
                'gender' => $genders[array_rand($genders)],
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 2 admin users
        for ($i = 0; $i < 2; $i++) {
            User::create([
                'userid_DI' => 'admin_' . ($i + 1),
                'username' => 'admin_' . ($i + 1),
                'email' => 'admin' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'final_cc_cname_DI' => 'United States',
                'LoE_DI' => 'PhD',
                'YoB' => 1985,
                'gender' => 'Male',
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 3 instructor users
        for ($i = 0; $i < 3; $i++) {
            User::create([
                'userid_DI' => 'instructor_' . ($i + 1),
                'username' => 'instructor_' . ($i + 1),
                'email' => 'instructor' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'final_cc_cname_DI' => $countries[array_rand($countries)],
                'LoE_DI' => 'PhD',
                'YoB' => 1980,
                'gender' => 'Female',
                'role' => 'instructor',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Users seeded successfully!');
    }

    private function seedCategories()
    {
        $this->command->info('Seeding categories...');
        
        $categories = [
            'Programming', 'Data Science', 'Business', 'Design', 'Marketing',
            'Personal Development', 'Health & Fitness', 'Music', 'Photography', 'Language'
        ];
        
        foreach ($categories as $category) {
            Category::create([
                'name' => $category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Categories seeded successfully!');
    }

    private function seedCourses()
    {
        $this->command->info('Seeding 100 courses...');
        
        $universities = ['MIT', 'Stanford', 'Harvard', 'Coursera', 'edX', 'Udemy', 'Local University'];
        $difficulties = ['Beginner', 'Intermediate', 'Advanced'];
        $statuses = ['pending', 'approved', 'rejected', 'unavailable'];
        
        for ($i = 0; $i < 100; $i++) {
            Course::create([
                'course_name' => 'Course ' . ($i + 1),
                'university' => $universities[array_rand($universities)],
                'difficulty_level' => $difficulties[array_rand($difficulties)],
                'course_rating' => 0,
                'course_url' => 'courses/course_' . ($i + 1),
                'image' => 'images/course_' . ($i + 1) . '.jpg',
                'course_description' => 'Description for course ' . ($i + 1),
                'price' => rand(0, 1000000),
                'skills' => 'Skill ' . ($i + 1),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Courses seeded successfully!');
    }

    private function seedStudents()
    {
        $this->command->info('Seeding students...');
        
        $users = User::where('role', 'student')->get();
        $learningGoals = [
            'Career advancement', 'Skill development', 'Personal growth', 'Academic improvement', 'Certification'
        ];
        
        foreach ($users as $user) {
            Student::create([
                'user_id' => $user->id,
                'learning_goals' => $learningGoals[array_rand($learningGoals)],
                'total_courses_completed' => rand(0, 3),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Students seeded successfully!');
    }

    private function seedAdmins()
    {
        $this->command->info('Seeding admins...');
        
        $adminUsers = User::where('role', 'admin')->get();
        
        foreach ($adminUsers as $adminUser) {
            Admins::create([
                'user_id' => $adminUser->id,
                'admin_level' => 'organization',
                'activity_log' => 'Initial setup for admin ' . $adminUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Admins seeded successfully!');
    }

    private function seedInstructors()
    {
        $this->command->info('Seeding instructors...');
        
        $instructorUsers = User::where('role', 'instructor')->get();
        
        foreach ($instructorUsers as $user) {
            Instructors::create([
                'user_id' => $user->id,
                'name' => 'Instructor ' . $user->id,
                'bio' => 'Expert in various fields with years of experience.',
                'organization' => 'Online Learning Platform',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Instructors seeded successfully!');
    }

    private function seedCourseInstructors()
    {
        $this->command->info('Seeding course_instructors...');
        
        $instructors = Instructors::all();
        $courses = Course::all();
        
        if ($instructors->isEmpty() || $courses->isEmpty()) {
            $this->command->warn('No instructors or courses found. Skipping course_instructors seeding.');
            return;
        }
        
        foreach ($courses as $course) {
            // Assign 1-2 instructors per course
            $selectedInstructors = $instructors->random(rand(1, 2));
            
            foreach ($selectedInstructors as $instructor) {
                Course_Instructors::create([
                    'course_id' => $course->id,
                    'instructor_id' => $instructor->id,
                ]);
            }
        }
        
        $this->command->info('Course instructors seeded successfully!');
    }

    private function seedInteractions()
    {
        $this->command->info('Seeding interactions...');
        
        $students = User::where('role', 'student')->get();
        $courses = Course::all();
        
        foreach ($students as $student) {
            // Each student interacts with 1-5 courses
            $interactionCount = rand(1, 5);
            $selectedCourses = $courses->random($interactionCount);
            
            foreach ($selectedCourses as $course) {
                $viewed = rand(0, 100) < 80;
                $explored = $viewed ? (rand(0, 100) < 60) : false;
                $certified = $explored ? (rand(0, 100) < 40) : false;
                
                $startTime = now()->subDays(rand(1, 180));
                $lastEvent = $viewed ? $startTime->copy()->addDays(rand(1, 30)) : null;
                
                $rating = null;
                if ($viewed) {
                    $rating = $certified ? rand(40, 50) / 10 : rand(20, 50) / 10;
                }
                
                Interaction::create([
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'rating' => $rating,
                    'viewed' => $viewed,
                    'explored' => $explored,
                    'certified' => $certified,
                    'start_time' => $startTime,
                    'last_event' => $lastEvent,
                    'nevents' => $viewed ? rand(1, 50) : 0,
                    'ndays_act' => $viewed ? rand(1, 15) : 0,
                    'nplay_video' => $viewed ? rand(0, 20) : 0,
                    'nchapters' => $viewed ? rand(0, 10) : 0,
                    'nforum_posts' => $viewed ? rand(0, 5) : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Interactions seeded successfully!');
    }

    private function seedEnrollments()
    {
        $this->command->info('Seeding enrollments...');
        
        $interactions = Interaction::where('viewed', true)->get();
        
        foreach ($interactions as $interaction) {
            if (!Enrollment::where('user_id', $interaction->user_id)
                ->where('course_id', $interaction->course_id)
                ->exists()) {
                $status = $interaction->certified ? 'completed' : 'active';
                $completedAt = $interaction->certified ? now()->subDays(rand(1, 60)) : null;
                $expiresAt = now()->addDays(rand(30, 180));
                
                Enrollment::create([
                    'user_id' => $interaction->user_id,
                    'course_id' => $interaction->course_id,
                    'enrolled_at' => $interaction->start_time,
                    'completed_at' => $completedAt,
                    'expires_at' => $expiresAt,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Enrollments seeded successfully!');
    }

    private function seedCertificates()
    {
        $this->command->info('Seeding certificates...');
        
        $enrollments = Enrollment::where('status', 'completed')->get();
        
        foreach ($enrollments as $enrollment) {
            Certificate::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'enrollment_id' => $enrollment->id,
                'certificate_code' => 'CERT-' . Str::random(10),
                'issued_at' => $enrollment->completed_at,
                'download_url' => 'certificates/' . Str::random(20) . '.pdf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Certificates seeded successfully!');
    }

    private function seedCoupons()
    {
        $this->command->info('Seeding coupons...');
        
        $coupons = [
            ['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 10, 'min_order' => 100000, 'usage_limit' => 50],
            ['code' => 'FIXED5000', 'discount_type' => 'fixed', 'discount_value' => 5000, 'min_order' => 50000, 'usage_limit' => 30],
            ['code' => 'WELCOME20', 'discount_type' => 'percent', 'discount_value' => 20, 'min_order' => 200000, 'usage_limit' => 100],
        ];
        
        foreach ($coupons as $coupon) {
            Coupon::create([
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'discount_value' => $coupon['discount_value'],
                'min_order' => $coupon['min_order'],
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(90),
                'usage_limit' => $coupon['usage_limit'],
                'used_count' => rand(0, $coupon['usage_limit'] / 2),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Coupons seeded successfully!');
    }

    private function seedPayments()
    {
        $this->command->info('Seeding payments...');
        
        $enrollments = Enrollment::inRandomOrder()->take(30)->get();
        $methods = ['momo', 'zalopay', 'bank_transfer', 'vnpay'];
        $coupons = Coupon::all();
        
        foreach ($enrollments as $enrollment) {
            $couponId = rand(0, 100) < 20 ? $coupons->random()->id : null;
            
            Payment::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'amount' => rand(50000, 500000),
                'method' => $methods[array_rand($methods)],
                'transaction_code' => 'TXN-' . Str::random(10),
                'coupon_id' => $couponId,
                'status' => 'completed',
                'payment_date' => $enrollment->enrolled_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Payments seeded successfully!');
    }

    private function seedLessons()
    {
        $this->command->info('Seeding lessons...');
        
        $courses = Course::all();
        
        foreach ($courses as $course) {
            $lessonCount = rand(3, 10);
            
            for ($i = 1; $i <= $lessonCount; $i++) {
                Lesson::create([
                    'course_id' => $course->id,
                    'title' => "Lesson $i: Topic $i",
                    'video_url' => 'videos/lesson_' . Str::random(10) . '.mp4',
                    'duration' => rand(5, 30),
                    'is_preview' => rand(0, 100) < 30,
                    'sort_order' => $i,
                    'status' => 'approved',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Lessons seeded successfully!');
    }

    private function seedLessonProgress()
{
    $this->command->info('Seeding lesson progress...');
    
    $enrollments = Enrollment::all();
    $statuses = ['not_started', 'in_progress', 'completed'];
    
    foreach ($enrollments as $enrollment) {
        $lessons = Lesson::where('course_id', $enrollment->course_id)->get();
        
        foreach ($lessons as $lesson) {
            $status = $enrollment->status == 'completed' ? 'completed' : $statuses[array_rand($statuses)];
            $completedAt = $status == 'completed' ? now()->subDays(rand(1, 60)) : null;
            
            LessonProgress::create([
                'user_id' => $enrollment->user_id,
                'lesson_id' => $lesson->id,
                'status' => $status,
                'completed_at' => $completedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    $this->command->info('Lesson progress seeded successfully!');
}

    private function seedQuizzes()
    {
        $this->command->info('Seeding quizzes...');
        
        $lessons = Lesson::all();
        
        foreach ($lessons as $lesson) {
            $quizCount = rand(0, 1); // 0 or 1 quiz per lesson
            
            for ($i = 1; $i <= $quizCount; $i++) {
                Quiz::create([
                    'lesson_id' => $lesson->id,
                    'title' => "Quiz for Lesson {$lesson->id}",
                    'max_attempts' => 3,
                    'time_limit' => rand(10, 30),
                    'is_visible' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Quizzes seeded successfully!');
    }

   private function seedQuizResults()
{
    $this->command->info('Seeding quiz results...');
    
    $lessonProgresses = LessonProgress::where('status', 'completed')->get();
    
    foreach ($lessonProgresses as $progress) {
        $quizzes = Quiz::where('lesson_id', $progress->lesson_id)->get();
        
        foreach ($quizzes as $quiz) {
            // Ensure completed_at is a Carbon instance and not null
            $completedAt = $progress->completed_at 
                ? \Carbon\Carbon::parse($progress->completed_at)
                : now()->subDays(rand(1, 60));
            
            QuizResult::create([
                'user_id' => $progress->user_id,
                'quiz_id' => $quiz->id,
                'attempt_number' => rand(1, 3),
                'score' => rand(60, 100),
                'started_at' => $completedAt->subMinutes(rand(10, 60)),
                'completed_at' => $completedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
    
    $this->command->info('Quiz results seeded successfully!');
}

    private function seedReviews()
    {
        $this->command->info('Seeding reviews...');
        
        $interactions = Interaction::whereNotNull('rating')->get();
        
        foreach ($interactions as $interaction) {
            if (!Review::where('user_id', $interaction->user_id)
                ->where('course_id', $interaction->course_id)
                ->exists()) {
                Review::create([
                    'user_id' => $interaction->user_id,
                    'course_id' => $interaction->course_id,
                    'rating' => round($interaction->rating),
                    'comment' => 'This course was very helpful!',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Reviews seeded successfully!');
    }

    private function seedForumPosts()
    {
        $this->command->info('Seeding forum posts...');
        
        $interactions = Interaction::where('nforum_posts', '>', 0)->get();
        
        foreach ($interactions as $interaction) {
            $postCount = min($interaction->nforum_posts, 3); // Limit to 3 posts per interaction
            
            for ($i = 1; $i <= $postCount; $i++) {
                ForumPost::create([
                    'user_id' => $interaction->user_id,
                    'course_id' => $interaction->course_id,
                    'title' => "Discussion Topic $i",
                    'content' => "This is a discussion post about topic $i for course {$interaction->course_id}.",
                    'flagged' => rand(0, 100) < 5, // 5% chance of being flagged
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Forum posts seeded successfully!');
    }

    private function seedSimilarityMatrix()
    {
        $this->command->info('Seeding similarity matrix...');
        
        $courses = Course::inRandomOrder()->take(20)->get();
        
        foreach ($courses as $course1) {
            foreach ($courses as $course2) {
                if ($course1->id < $course2->id) { // Avoid duplicates and self-similarity
                    SimilarityMatrix::create([
                        'course_id_1' => $course1->id,
                        'course_id_2' => $course2->id,
                        'similarity_score' => rand(10, 90) / 100,
                    ]);
                }
            }
        }
        
        $this->command->info('Similarity matrix seeded successfully!');
    }
}