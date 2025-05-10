<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Course;
use App\Models\Interaction;
use App\Models\Admins;
use App\Models\Category;
use App\Models\Certificate;
use App\Models\Coupon;
use App\Models\Course_Instructors;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\Instructors;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Review;
use App\Models\Student;
use App\Models\SimilarityMatrix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Users - Create 100 diverse users
        $this->seedUsers();
        
        // Seed Courses - Already seeded with 1000 records
        $this->command->info('Courses are already seeded with 1000 records.');
        
        // Seed Categories
        $this->seedCategories();
        
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
        // $this->seedSimilarityMatrix();
    }
    
    /**
     * Seed users table with 100 diverse users
     */
    private function seedUsers()
    {
        $this->command->info('Seeding 100 users...');
        
        $educationLevels = ['High School', 'Bachelor', 'Master', 'PhD', 'Associate', 'Unknown'];
        $countries = [
            'United States', 'India', 'United Kingdom', 'Canada', 'Australia', 
            'Germany', 'France', 'Brazil', 'Japan', 'China', 'Mexico', 'Spain',
            'Italy', 'Russia', 'South Korea', 'Netherlands', 'Sweden', 'Switzerland',
            'Singapore', 'South Africa','VietNam', 'Unknown'
        ];
        $genders = ['Male', 'Female', 'Other', 'Prefer not to say', null];
        
        // Create 98 student users
        for ($i = 0; $i < 98; $i++) {
            $birthYear = rand(1960, 2005);
            
            User::create([
                'userid_DI' => 'user_' . Str::uuid(),
                'email' => 'user' . ($i + 3) . '@example.com',
                'password' => Hash::make('password'),
                'final_cc_cname_DI' => $countries[array_rand($countries)],
                'LoE_DI' => $educationLevels[array_rand($educationLevels)],
                'YoB' => $birthYear,
                'gender' => $genders[array_rand($genders)],
                'role' => 'student',
            ]);
        }
        // Create 1 admin user
        User::create([
            'userid_DI' => 'admin_user',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'final_cc_cname_DI' => 'United States',
            'LoE_DI' => 'PhD',
            'YoB' => 1985,
            'gender' => 'Male',
            'role' => 'admin',
        ]);
        // Create 1 instructor user
        for ($i = 0; $i < 98; $i++) {
        User::create([
            'userid_DI' => 'instructor_'. ($i + 3),
            'email' => 'instructor'.($i + 3).'@example.com',
            'password' => Hash::make('password'),
            'final_cc_cname_DI' => $countries[array_rand($countries)],
            'LoE_DI' => 'PhD',
            'YoB' => 1980,
            'gender' => 'Female',
            'role' => 'instructor',
        ]);
    }
        
        $this->command->info('Users seeded successfully!');
    }
    
    /**
     * Seed categories table
     */
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
    
    /**
     * Seed students table
     */
    private function seedStudents()
    {
        $this->command->info('Seeding students...');
        
        $users = User::where('role', 'student')->get();
        $learningGoals = [
            'Career advancement', 'Skill development', 'Personal growth', 'Academic improvement', 'Certification'
        ];
        $interests = [
            'Programming', 'Data Analysis', 'Graphic Design', 'Marketing', 'Leadership', 'Music Production'
        ];
        
        foreach ($users as $user) {
            Student::create([
                'user_id' => $user->id,
                'learning_goals' => $learningGoals[array_rand($learningGoals)],
                'interests' => $interests[array_rand($interests)],
                'total_courses_completed' => rand(0, 5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Students seeded successfully!');
    }
    
    /**
     * Seed admins table
     */
    private function seedAdmins()
    {
        $this->command->info('Seeding admins...');
        
        $adminUser = User::where('role', 'admin')->first();
        
        Admins::create([
            'user_id' => $adminUser->id,
            'admin_level' => 'organization',
            'activity_log' => 'Initial setup',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->command->info('Admins seeded successfully!');
    }
    
    /**
     * Seed instructors table
     */
    private function seedInstructors()
    {
        $this->command->info('Seeding instructors...');
        $instructorUser = User::where('role', 'instructor')->get();
        foreach ($instructorUser as $user) {
            Instructors::create([
                'user_id' => $user->id,
                'name' => 'Instructor ' . $user->id,
                'bio' => 'Expert in various fields with years of experience.',
                'avatar' => 'avatars/instructor_' . $user->id . '.jpg',
                'organization' => 'Online Learning Platform',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Instructors seeded successfully!');
    }
    
    /**
     * Seed course_instructors table
     */
    private function seedCourseInstructors()
    {
        $this->command->info('Seeding course_instructors...');
    
        // Retrieve all instructors
        $instructors = Instructors::all();
        
        if ($instructors->isEmpty()) {
            $this->command->warn('No instructors found. Skipping course_instructors seeding.');
            return;
        }
    
        // Retrieve all courses
        $courses = Course::all();
        
        if ($courses->isEmpty()) {
            $this->command->warn('No courses found. Skipping course_instructors seeding.');
            return;
        }
    
        // Shuffle courses to randomize assignments
        $availableCourses = $courses->shuffle();
    
        foreach ($instructors as $instructor) {
            // Determine number of courses to assign (e.g., 5-10 courses per instructor)
            $numCourses = rand(5, min(10, $availableCourses->count()));
            
            // Take a subset of available courses for this instructor
            $selectedCourses = $availableCourses->take($numCourses);
    
            foreach ($selectedCourses as $course) {
                // Check if this course-instructor pair already exists
                $exists = Course_Instructors::where('course_id', $course->id)
                                         ->where('instructor_id', $instructor->id)
                                         ->exists();
    
                if (!$exists) {
                    Course_Instructors::create([
                        'course_id' => $course->id,
                        'instructor_id' => $instructor->id,
                    ]);
                }
            }
    
            // Remove selected courses from available courses to avoid reuse
            $availableCourses = $availableCourses->diff($selectedCourses);
        }
    
        $this->command->info('Course instructors seeded successfully!');
    }
    /**
     * Seed interactions table with diverse interactions
     */
    private function seedInteractions()
    {
        $this->command->info('Seeding interactions...');
        
        $users =User::where('role', 'student')->get();
        $totalCourses = Course::count();
        $this->command->info("Total courses available: {$totalCourses}");
        
        foreach ($users as $user) {
            $interactionCount = rand(5, 30);
            $this->command->info("Creating {$interactionCount} interactions for user {$user->id}");
            
            $courseIds = [];
            for ($i = 0; $i < $interactionCount; $i++) {
                $courseId = rand(1, $totalCourses);
                if (!in_array($courseId, $courseIds)) {
                    $courseIds[] = $courseId;
                }
            }
            
            foreach ($courseIds as $courseId) {
                $viewed = rand(0, 100) < 80;
                $explored = $viewed ? (rand(0, 100) < 60) : false;
                $certified = $explored ? (rand(0, 100) < 40) : false;
                
                $startTime = now()->subDays(rand(1, 365));
                $lastEvent = $viewed ? $startTime->copy()->addDays(rand(1, 30)) : null;
                
                $nevents = $viewed ? rand(1, 100) : 0;
                $ndaysAct = $viewed ? rand(1, 30) : 0;
                $nplayVideo = $viewed ? rand(0, 50) : 0;
                $nchapters = $viewed ? rand(0, 20) : 0;
                $nforumPosts = $viewed ? rand(0, 10) : 0;
                
                $rating = null;
                if ($viewed) {
                    if ($certified) {
                        $rating = rand(30, 50) / 10;
                    } elseif ($explored) {
                        $rating = rand(20, 50) / 10;
                    } else {
                        $rating = rand(0, 100) < 70 ? rand(10, 50) / 10 : null;
                    }
                }
                
                Interaction::create([
                    'user_id' => $user->id,
                    'course_id' => $courseId,
                    'rating' => $rating,
                    'viewed' => $viewed,
                    'explored' => $explored,
                    'certified' => $certified,
                    'start_time' => $startTime,
                    'last_event' => $lastEvent,
                    'nevents' => $nevents,
                    'ndays_act' => $ndaysAct,
                    'nplay_video' => $nplayVideo,
                    'nchapters' => $nchapters,
                    'nforum_posts' => $nforumPosts,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Interactions seeded successfully!');
    }
    
    /**
     * Seed enrollments table
     */
    private function seedEnrollments()
    {
        $this->command->info('Seeding enrollments...');
        
        $interactions = Interaction::where('viewed', true)->get();
        
        foreach ($interactions as $interaction) {
            $status = $interaction->certified ? 'completed' : 'active';
            $completedAt = $interaction->certified ? now()->subDays(rand(1, 100)) : null;
            $expiresAt = now()->addDays(rand(30, 365));
            
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
        
        $this->command->info('Enrollments seeded successfully!');
    }
    
    /**
     * Seed certificates table
     */
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
    
    /**
     * Seed coupons table
     */
    private function seedCoupons()
    {
        $this->command->info('Seeding coupons...');
        
        $coupons = [
            ['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 10, 'min_order' => 100000, 'usage_limit' => 100],
            ['code' => 'FIXED5000', 'discount_type' => 'fixed', 'discount_value' => 5000, 'min_order' => 50000, 'usage_limit' => 50],
            ['code' => 'WELCOME20', 'discount_type' => 'percent', 'discount_value' => 20, 'min_order' => 200000, 'usage_limit' => 200],
        ];
        
        foreach ($coupons as $coupon) {
            Coupon::create([
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'discount_value' => $coupon['discount_value'],
                'min_order' => $coupon['min_order'],
                'start_date' => now()->subDays(10),
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
    
    /**
     * Seed payments table
     */
    private function seedPayments()
    {
        $this->command->info('Seeding payments...');
        
        $enrollments = Enrollment::inRandomOrder()->take(50)->get();
        $methods = ['momo', 'zalopay', 'bank_transfer'];
        $coupons = Coupon::all();
        
        foreach ($enrollments as $enrollment) {
            $couponId = rand(0, 100) < 30 ? $coupons->random()->id : null;
            
            Payment::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'amount' => rand(50000, 1000000),
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
    
    /**
     * Seed lessons table
     */
    private function seedLessons()
    {
        $this->command->info('Seeding lessons...');
        $courses = Course::all();
        
        foreach ($courses as $course) {
            $lessonCount = rand(5, 15);
            
            for ($i = 1; $i <= $lessonCount; $i++) {
                Lesson::create([
                    'course_id' => $course->id,
                    'title' => "Lesson $i: Introduction to Topic $i",
                    'video_url' => 'videos/lesson_' . Str::random(10) . '.mp4',
                    'duration' => rand(5, 60),
                    'is_preview' => rand(0, 100) < 20,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Lessons seeded successfully!');
    }
    
    /**
     * Seed lesson_progress table
     */
    private function seedLessonProgress()
    {
        $this->command->info('Seeding lesson progress...');
        
        $enrollments = Enrollment::all();
        $statuses = ['not_started', 'in_progress', 'completed'];
        
        foreach ($enrollments as $enrollment) {
            $lessons = Lesson::where('course_id', $enrollment->course_id)->get();
            
            foreach ($lessons as $lesson) {
                $status = $enrollment->status == 'completed' ? 'completed' : $statuses[array_rand($statuses)];
                $completedAt = $status == 'completed' ? now()->subDays(rand(1, 100)) : null;
                
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
    
    /**
     * Seed quizzes table
     */
    private function seedQuizzes()
    {
        $this->command->info('Seeding quizzes...');
        
        $lessons = Lesson::all();
        
        foreach ($lessons as $lesson) {
            $quizCount = rand(0, 2);
            
            for ($i = 1; $i <= $quizCount; $i++) {
                Quiz::create([
                    'lesson_id' => $lesson->id,
                    'title' => "Quiz $i for Lesson {$lesson->id}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Quizzes seeded successfully!');
    }
    
    /**
     * Seed quiz_results table
     */
    private function seedQuizResults()
    {
        $this->command->info('Seeding quiz results...');
        
        $lessonProgresses = LessonProgress::where('status', 'completed')->get();
        
        foreach ($lessonProgresses as $progress) {
            $quizzes = Quiz::where('lesson_id', $progress->lesson_id)->get();
            
            foreach ($quizzes as $quiz) {
                QuizResult::create([
                    'user_id' => $progress->user_id,
                    'quiz_id' => $quiz->id,
                    'score' => rand(50, 100),
                    'completed_at' => $progress->completed_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Quiz results seeded successfully!');
    }
    
    /**
     * Seed reviews table
     */
    private function seedReviews()
    {
        $this->command->info('Seeding reviews...');
        
        $interactions = Interaction::whereNotNull('rating')->get();
        
        foreach ($interactions as $interaction) {
            Review::create([
                'user_id' => $interaction->user_id,
                'course_id' => $interaction->course_id,
                'rating' => round($interaction->rating),
                'comment' => 'Great course, learned a lot!',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Reviews seeded successfully!');
    }
    
    /**
     * Seed forum_posts table
     */
    private function seedForumPosts()
    {
        $this->command->info('Seeding forum posts...');
        
        $interactions = Interaction::where('nforum_posts', '>', 0)->get();
        
        foreach ($interactions as $interaction) {
            $postCount = $interaction->nforum_posts;
            
            for ($i = 1; $i <= $postCount; $i++) {
                ForumPost::create([
                    'user_id' => $interaction->user_id,
                    'course_id' => $interaction->course_id,
                    'title' => "Discussion Topic $i",
                    'content' => "This is a discussion post about topic $i.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Forum posts seeded successfully!');
    }
    
    /**
     * Seed similarity_matrix table
     */
    private function seedSimilarityMatrix()
    {
        $this->command->info('Seeding similarity matrix...');
        
        $courses = Course::inRandomOrder()->take(20)->get();
        
        foreach ($courses as $course1) {
            foreach ($courses as $course2) {
                if ($course1->id != $course2->id) {
                    SimilarityMatrix::create([
                        'course_id_1' => $course1->id,
                        'course_id_2' => $course2->id,
                        'similarity_score' => rand(0, 100) / 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        
        $this->command->info('Similarity matrix seeded successfully!');
    }
}