<?php

namespace Database\Seeders;

use App\Models\AdminAccount;
use App\Models\Course_Instructors;
use App\Models\CourseCategory;
use App\Models\CourseReview;
use App\Models\InstructorAccount;
use App\Models\InstructorRequest;
use App\Models\Media;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\RevenueDistribution;
use App\Models\RevenueSession;
use App\Models\StudentCategory;
use App\Models\UserAnswer;
use App\Models\Course;
use App\Models\Category;
use App\Models\Student;
use App\Models\Admins;
use App\Models\Instructors;
use App\Models\Interaction;
use App\Models\Enrollment;
use App\Models\Certificate;
use App\Models\CertificateRule;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\Review;
use App\Models\ForumPost;
use App\Models\SimilarityMatrix;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Report;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedAdmins();
        $this->seedInstructors();
        $this->seedStudents();
        $this->seedCategories();
        $this->seedCourses();
        $this->seedCourseCategories();
        $this->seedStudentCategories();
        $this->seedCourseReviews();
        $this->seedEnrollments();
        $this->seedCertificates();
        $this->seedCertificateRules();
        $this->seedCoupons();
        $this->seedPayments();
        $this->seedAuditLogs();
        $this->seedRevenueSessions();
        $this->seedRevenueDistributions();
        $this->seedLessons();
        $this->seedLessonProgress();
        $this->seedQuizzes();
        $this->seedQuestions();
        $this->seedQuestionChoices();
        $this->seedQuizResults();
        $this->seedUserAnswers();
        $this->seedReviews();
        $this->seedForumPosts();
        $this->seedReports();
    }

    private function seedUsers()
    {
        $this->command->info('Seeding 50 users...');
        
        $genders = ['Male', 'Female', 'Other', 'Prefer not to say', null];
        
        // Create 45 student users
        for ($i = 0; $i < 45; $i++) {
            $birthYear = rand(1980, 2005);
            $birthMonth = rand(1, 12);
            $birthDay = rand(1, 28);

            $birthdate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);

            User::create([
                'username' => 'user_' . ($i + 1),
                'email' => 'user' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'birthdate' => $birthdate,
                'gender' => $genders[array_rand($genders)],
                'role' => 'student',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 2 admin users
        for ($i = 0; $i < 2; $i++) {
            User::create([
                'username' => 'admin_' . ($i + 1),
                'email' => 'admin' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'birthdate' => '1980-01-01',
                'gender' => 'Male',
                'role' => 'admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 3 instructor users
        for ($i = 0; $i < 3; $i++) {
            User::create([
                'username' => 'instructor_' . ($i + 1),
                'email' => 'instructor' . ($i + 1) . '@example.com',
                'password' => Hash::make('password'),
                'birthdate' => '1980-01-01',
                'gender' => 'Female',
                'role' => 'instructor',
                'status' => 'active',
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
            'Programming' => ['Web Development', 'Mobile Development', 'Game Development'],
            'Data Science' => ['Machine Learning', 'Data Analysis', 'Deep Learning'],
            'Business' => ['Entrepreneurship', 'Finance', 'Management'],
            'Design' => ['Graphic Design', 'UI/UX Design', '3D Design'],
            'Marketing' => ['Digital Marketing', 'SEO', 'Content Marketing'],
            'Personal Development' => ['Productivity', 'Leadership', 'Career Development'],
            'Health & Fitness' => ['Nutrition', 'Yoga', 'Workout'],
            'Music' => ['Instruments', 'Music Theory', 'Songwriting'],
            'Photography' => ['Portrait Photography', 'Photo Editing', 'Videography'],
            'Language' => ['English', 'Spanish', 'Chinese'],
        ];

        foreach ($categories as $parentName => $subcategories) {
            $parent = Category::create([
                'name' => $parentName,
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($subcategories as $childName) {
                Category::create([
                    'name' => $childName,
                    'parent_id' => $parent->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Categories and subcategories seeded successfully!');
    }

    private function seedCourses()
    {
        $this->command->info('Seeding 100 courses...');

        $difficulties = ['Beginner', 'Intermediate', 'Advanced'];
        $statuses = ['pending', 'approved', 'rejected', 'unavailable', 'draft'];

        $instructors = Instructors::all();

        if ($instructors->isEmpty()) {
            $this->command->error('No instructors found. Please seed instructors first.');
            return;
        }

        for ($i = 0; $i < 100; $i++) {
            $instructor = $instructors->random();

            Course::create([
                'instructor_id' => $instructor->id,
                'course_name' => 'Course_' . ($i + 1),
                'difficulty_level' => $difficulties[array_rand($difficulties)],
                'course_rating' => 0,
                'course_url' => 'course_' . ($i + 1),
                'image' => 'images/course_' . ($i + 1) . '.jpg',
                'course_description' => 'Description for course ' . ($i + 1),
                'price' => rand(0, 1000000),
                'skills' => 'Skill ' . ($i + 1),
                'status' => $statuses[array_rand($statuses)],
                'is_certificate_enabled' => rand(0, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Courses seeded successfully!');
    }

    private function seedCourseCategories()
    {
        $this->command->info('Seeding CourseCategory...');
        
        $courses = Course::all();
        $categories = Category::all();
        
        if ($courses->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('No courses or categories found. Skipping CourseCategory seeding.');
            return;
        }
        
        foreach ($courses as $course) {
            $selectedCategories = $categories->random(rand(1, 3));
            
            foreach ($selectedCategories as $category) {
                CourseCategory::create([
                    'course_id' => $course->id,
                    'category_id' => $category->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Course categories seeded successfully!');
    }

    private function seedStudents()
    {
        $this->command->info('Seeding students...');
        
        $users = User::where('role', 'student')->get();
        $learningGoals = [
            'Career advancement', 'Skill development', 'Personal growth', 'Academic improvement', 'Certification'
        ];
        $educationLevels = ['Beginner', 'Intermediate', 'Advanced', 'Unknown'];
        
        foreach ($users as $user) {
            Student::create([
                'user_id' => $user->id,
                'learning_goals' => $learningGoals[array_rand($learningGoals)],
                'LoE_DI' => $educationLevels[array_rand($educationLevels)],
                'total_courses_completed' => rand(0, 3),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Students seeded successfully!');
    }

    private function seedStudentCategories()
    {
        $this->command->info('Seeding student_category...');
        
        $students = Student::all();
        $categories = Category::all();
        
        if ($students->isEmpty() || $categories->isEmpty()) {
            $this->command->warn('No students or categories found. Skipping student_category seeding.');
            return;
        }
        
        foreach ($students as $student) {
            $selectedCategories = $categories->random(rand(1, 3));
            
            foreach ($selectedCategories as $category) {
                StudentCategory::create([
                    'student_id' => $student->id,
                    'category_id' => $category->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Student categories seeded successfully!');
    }

    private function seedAdmins()
    {
        $this->command->info('Seeding admins...');
        
        $adminUsers = User::where('role', 'admin')->get();
        $adminLevels = ['organization', 'program'];
        
        foreach ($adminUsers as $adminUser) {
            Admins::create([
                'user_id' => $adminUser->id,
                'admin_level' => $adminLevels[array_rand($adminLevels)],
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
        $banks = ['Bank of America', 'HSBC', 'Vietcombank', 'Techcombank'];
        
        foreach ($instructorUsers as $user) {
            Instructors::create([
                'user_id' => $user->id,
                'name' => 'Instructor ' . $user->id,
                'bio' => 'Expert in various fields with years of experience.',
                'organization' => 'Online Learning Platform',
                'bank_account' => 'ACC' . rand(1000000, 9999999),
                'bank_name' => $banks[array_rand($banks)],
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

        $instructorCount = $instructors->count();
        $seededCount = 0;
        $skippedCourses = [];

        $courses = $courses->shuffle();

        foreach ($courses as $index => $course) {
            if (Course_Instructors::where('course_id', $course->id)->exists()) {
                $skippedCourses[] = $course->course_name;
                continue;
            }

            $instructorIndex = $index % $instructorCount;
            $instructor = $instructors[$instructorIndex];

            try {
                DB::transaction(function () use ($course, $instructor, &$seededCount) {
                    Course_Instructors::create([
                        'course_id' => $course->id,
                        'instructor_id' => $instructor->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $seededCount++;
                });
            } catch (\Illuminate\Database\QueryException $e) {
                $this->command->error("Failed to assign instructor ID {$instructor->id} to course ID {$course->id}: {$e->getMessage()}");
                $skippedCourses[] = $course->course_name;
            }
        }

        if ($seededCount > 0) {
            $this->command->info("Successfully seeded {$seededCount} course-instructor relationships.");
        } else {
            $this->command->warn('No course-instructor relationships were seeded.');
        }

        if (!empty($skippedCourses)) {
            $this->command->warn('The following courses were skipped: ' . implode(', ', $skippedCourses));
        }
    }

    private function seedCourseReviews()
    {
        $this->command->info('Seeding course_reviews...');
        
        $courses = Course::where('status', 'pending')->inRandomOrder()->take(20)->get();
        $statuses = ['approved', 'rejected'];
        $admin = Admins::first();

        foreach ($courses as $course) {
            CourseReview::create([
                'course_id' => $course->id,
                'admin_id' => $admin->id,
                'status' => $statuses[array_rand($statuses)],
                'notes' => 'Review notes for course ' . $course->id,
                'reviewed_at' => now()->subDays(rand(1, 30)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Course reviews seeded successfully!');
    }

    private function seedEnrollments()
    {
        $this->command->info('Seeding enrollments...');

        $students = User::where('role', 'student')->get();
        $courses = Course::all();
        $statuses = ['active', 'completed'];

        foreach ($students as $student) {
            $enrolledCourses = $courses->random(rand(3, 5));

            foreach ($enrolledCourses as $course) {
                if (!Enrollment::where('user_id', $student->id)
                    ->where('course_id', $course->id)
                    ->exists()) {

                    $status = $statuses[array_rand($statuses)];
                    $completedAt = $status === 'completed' ? now()->subDays(rand(1, 60)) : null;
                    $enrolledAt = now()->subDays(rand(30, 90));

                    Enrollment::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'enrolled_at' => $enrolledAt,
                        'completed_at' => $completedAt,
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Enrollments seeded successfully!');
    }

    private function seedCertificates()
    {
        $this->command->info('Seeding certificates...');
        
        $enrollments = Enrollment::where('status', 'completed')->get();
        
        foreach ($enrollments as $enrollment) {
            $course = Course::find($enrollment->course_id);
            if ($course && $course->is_certificate_enabled) {
                $instructor_id = $course->instructor_id;
                
                Certificate::create([
                    'user_id' => $enrollment->user_id,
                    'instructor_id' => $instructor_id,
                    'course_id' => $enrollment->course_id,
                    'enrollment_id' => $enrollment->id,
                    'certificate_code' => 'CERT-' . Str::random(10),
                    'issued_at' => $enrollment->completed_at,
                    'download_url' => 'certificates/' . Str::random(20) . '.pdf',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Certificates seeded successfully!');
    }

    private function seedCertificateRules()
    {
        $this->command->info('Seeding certificate_rules...');
        
        $courses = Course::where('is_certificate_enabled', 1)->get();
        $versionRules = ['latest', 'any'];
        
        foreach ($courses as $course) {
            CertificateRule::create([
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'lesson_completion_percent' => rand(80, 100),
                'lesson_version_rule' => $versionRules[array_rand($versionRules)],
                'quiz_min_score' => rand(60, 80),
                'quiz_version_rule' => $versionRules[array_rand($versionRules)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Certificate rules seeded successfully!');
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
        $methods = ['momo', 'zalopay', 'bank_transfer', 'vnpay', 'paypal'];
        $coupons = Coupon::all();
        $revenueSessions = RevenueSession::all();
        
        foreach ($enrollments as $enrollment) {
            $couponId = rand(0, 100) < 20 ? $coupons->random()->id : null;
            $revenueSessionId = $revenueSessions->isNotEmpty() ? $revenueSessions->random()->id : null;
            
            Payment::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'amount' => rand(50000, 500000),
                'method' => $methods[array_rand($methods)],
                'transaction_code' => 'TXN-' . Str::random(10),
                'coupon_id' => $couponId,
                'revenue_session_id' => $revenueSessionId,
                'status' => 'completed',
                'payment_date' => $enrollment->enrolled_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Payments seeded successfully!');
    }

    private function seedAuditLogs()
    {
        $this->command->info('Seeding audit_logs...');
        
        $payments = Payment::all();
        $actions = ['created', 'status_updated', 'refunded'];
        
        foreach ($payments as $payment) {
            AuditLog::create([
                'payment_id' => $payment->id,
                'action' => $actions[array_rand($actions)],
                'details' => 'Action performed on payment ' . $payment->id,
                'user_id' => $payment->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Audit logs seeded successfully!');
    }

    private function seedRevenueSessions()
    {
        $this->command->info('Seeding revenue_sessions...');
        
        $months = [1, 2, 3];
        $year = 2025;
        $statuses = ['open', 'closed', 'distributed'];
        
        foreach ($months as $month) {
            RevenueSession::create([
                'month' => $month,
                'year' => $year,
                'total_revenue' => rand(10000000, 50000000),
                'admin_share' => rand(3000000, 15000000),
                'instructor_share' => rand(7000000, 35000000),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Revenue sessions seeded successfully!');
    }

    private function seedRevenueDistributions()
    {
        $this->command->info('Seeding revenue_distributions...');

        $revenueSessions = RevenueSession::all();
        $courses = Course::whereNotNull('instructor_id')->get();
        $statuses = ['pending', 'completed', 'failed'];

        foreach ($courses as $course) {
            $revenueSession = $revenueSessions->random();

            RevenueDistribution::create([
                'revenue_session_id' => $revenueSession->id,
                'instructor_id' => $course->instructor_id,
                'course_id' => $course->id,
                'revenue_amount' => rand(500000, 5000000),
                'instructor_share' => rand(350000, 3500000),
                'status' => $statuses[array_rand($statuses)],
                'transaction_code' => 'TXN-' . Str::upper(Str::random(10)),
                'distributed_at' => now()->subDays(rand(1, 30)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Revenue distributions seeded successfully!');
    }

    private function seedLessons()
    {
        $this->command->info('Seeding lessons with versioning...');

        $courses = Course::all();
        $statuses = ['pending', 'approved'];

        foreach ($courses as $course) {
            $lessonCount = rand(3, 10);

            for ($i = 1; $i <= $lessonCount; $i++) {
                $originalLesson = Lesson::create([
                    'course_id' => $course->id,
                    'title' => "Lesson $i: Topic $i (v1)",
                    'video_url' => 'videos/lesson_' . Str::random(10) . '.mp4',
                    'duration' => rand(5, 30),
                    'is_preview' => rand(0, 100) < 30,
                    'sort_order' => $i,
                    'status' => $statuses[array_rand($statuses)],
                    'origin_id' => null,
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $versionCount = rand(0, 2);

                for ($v = 1; $v <= $versionCount; $v++) {
                    Lesson::create([
                        'course_id' => $course->id,
                        'title' => "Lesson $i: Topic $i (v" . ($v + 1) . ")",
                        'video_url' => 'videos/lesson_' . Str::random(10) . '_v' . ($v + 1) . '.mp4',
                        'duration' => rand(5, 30),
                        'is_preview' => rand(0, 100) < 30,
                        'sort_order' => $i,
                        'status' => $statuses[array_rand($statuses)],
                        'origin_id' => $originalLesson->id,
                        'version' => $v + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Lessons with versioning seeded successfully!');
    }

    private function seedLessonProgress()
    {
        $this->command->info('Seeding lesson_progress...');
        
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
            $quizCount = rand(0, 2);
            
            for ($i = 1; $i <= $quizCount; $i++) {
                Quiz::create([
                    'lesson_id' => $lesson->id,
                    'title' => "Quiz $i for Lesson {$lesson->id}",
                    'max_attempts' => 3,
                    'time_limit' => rand(10, 30),
                    'is_visible' => true,
                    'version' => 1,
                    'origin_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Quizzes seeded successfully!');
    }

    private function seedQuestions()
    {
        $this->command->info('Seeding questions...');
        
        $quizzes = Quiz::all();
        $questionTypes = ['multiple_choice', 'true_false'];
        
        foreach ($quizzes as $quiz) {
            $questionCount = rand(3, 5);
            
            for ($i = 1; $i <= $questionCount; $i++) {
                Question::create([
                    'quiz_id' => $quiz->id,
                    'title' => "Question $i for Quiz {$quiz->id}",
                    'question_type' => $questionTypes[array_rand($questionTypes)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Questions seeded successfully!');
    }

    private function seedQuestionChoices()
    {
        $this->command->info('Seeding question_choices...');
        
        $questions = Question::whereIn('question_type', ['multiple_choice', 'true_false'])->get();
        
        foreach ($questions as $question) {
            $choiceCount = $question->question_type === 'multiple_choice' ? 4 : 2;
            
            for ($i = 1; $i <= $choiceCount; $i++) {
                $isCorrect = $i === 1;
                
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'content' => $question->question_type === 'true_false' 
                        ? ($i === 1 ? 'True' : 'False') 
                        : "Choice $i for Question {$question->id}",
                    'is_correct' => $isCorrect,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Question choices seeded successfully!');
    }

    private function seedQuizResults()
    {
        $this->command->info('Seeding quiz_results...');
        
        $lessonProgresses = LessonProgress::where('status', 'completed')->get();
        
        foreach ($lessonProgresses as $progress) {
            $quizzes = Quiz::where('lesson_id', $progress->lesson_id)->get();
            
            foreach ($quizzes as $quiz) {
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

    private function seedUserAnswers()
    {
        $this->command->info('Seeding user_answers...');
        
        $quizResults = QuizResult::all();
        
        foreach ($quizResults as $quizResult) {
            $questions = Question::where('quiz_id', $quizResult->quiz_id)->get();
            
            foreach ($questions as $question) {
                $choice = null;
                $isCorrect = null;
                
                if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
                    $choices = QuestionChoice::where('question_id', $question->id)->get();
                    $choice = $choices->random();
                    $isCorrect = $choice->is_correct;
                } else {
                    $isCorrect = rand(0, 1);
                }
                
                UserAnswer::create([
                    'user_id' => $quizResult->user_id,
                    'quiz_result_id' => $quizResult->id,
                    'question_id' => $question->id,
                    'choice_id' => $choice ? $choice->id : null,
                    'is_correct' => $isCorrect,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('User answers seeded successfully!');
    }

    private function seedReviews()
    {
        $this->command->info('Seeding reviews...');
        
        $users = User::where('role', 'student')->inRandomOrder()->take(20)->get();
        $feedbackTypes = ['content_quality', 'instructor', 'platform_issue', 'not_interested'];
        
        foreach ($users as $user) {
            Review::create([
                'user_id' => $user->id,
                'course_id' => Course::inRandomOrder()->first()->id,
                'rating' => rand(1, 5),
                'comment' => 'This is a review for the course.',
                'feedback_type' => $feedbackTypes[array_rand($feedbackTypes)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Reviews seeded successfully!');
    }

    private function seedForumPosts()
    {
        $this->command->info('Seeding forum_posts...');
        
        $enrollments = Enrollment::all();
        $statuses = ['pending', 'approved', 'banned'];
        
        foreach ($enrollments as $enrollment) {
            $postCount = rand(0, 3);
            
            for ($i = 1; $i <= $postCount; $i++) {
                ForumPost::create([
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'title' => "Discussion Topic $i",
                    'content' => "This is a discussion post about topic $i for course {$enrollment->course_id}.",
                    'status' => $statuses[array_rand($statuses)],
                    'flagged' => rand(0, 100) < 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Forum posts seeded successfully!');
    }

    private function seedReports()
    {
        $this->command->info('Seeding reports...');
        
        $users = User::where('role', 'student')->inRandomOrder()->take(10)->get();
        $courses = Course::all();
        $admins = Admins::all();
        $reportTypes = ['inappropriate_content', 'technical_issue', 'copyright_violation', 'spam', 'other'];
        $statuses = ['pending', 'reviewed', 'resolved'];
        
        foreach ($users as $user) {
            $reportCount = rand(0, 2);
            
            for ($i = 1; $i <= $reportCount; $i++) {
                $status = $statuses[array_rand($statuses)];
                $adminId = $status !== 'pending' && $admins->isNotEmpty() ? $admins->random()->id : null;
                
                Report::create([
                    'user_id' => $user->id,
                    'course_id' => $courses->random()->id,
                    'reason' => 'Report reason ' . $i,
                    'report_type' => $reportTypes[array_rand($reportTypes)],
                    'status' => $status,
                    'admin_id' => $adminId,
                    'admin_notes' => $status !== 'pending' ? 'Admin notes for report ' . $i : null,
                    'reviewed_at' => $status !== 'pending' ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('Reports seeded successfully!');
    }

    private function seedSimilarityMatrix()
    {
        $this->command->info('Seeding similarity_matrix...');
        
        $courses = Course::inRandomOrder()->take(20)->get();
        
        foreach ($courses as $course1) {
            foreach ($courses as $course2) {
                if ($course1->id < $course2->id) {
                    SimilarityMatrix::create([
                        'course_id_1' => $course1->id,
                        'course_id_2' => $course2->id,
                        'similarity_score' => rand(10, 90) / 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        
        $this->command->info('Similarity matrix seeded successfully!');
    }
}