<?php
namespace Database\Seeders;

use App\Models\StudentCategory;
use App\Models\User;
use App\Models\Admins;
use App\Models\Instructors;
use App\Models\Students;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Certificate;
use App\Models\CertificateRule;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\AuditLog;
use App\Models\RevenueSession;
use App\Models\RevenueDistribution;
use App\Models\Review;
use App\Models\Report;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class DatabaseSeederNewNewNew extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        // $this->seedAdmins();
        $this->seedInstructors();
        $this->seedStudents();
        $this->seedCategories();
        $this->seedCourses();
        $this->seedStudentCategories();
        $this->seedLessons();
        $this->seedEnrollments();
        $this->seedLessonProgress();
        $this->seedCertificates();
        $this->seedCertificateRules();
        $this->seedCoupons();
        $this->seedPayments();
        // $this->seedAuditLogs();
        // $this->seedRevenueSessions();
        // $this->seedRevenueDistributions();
  
        $this->seedQuizzes();
        $this->seedQuestions();
        $this->seedQuestionChoices();
        $this->seedReviews();
        $this->seedReports();
    }

    private function seedUsers()
    {
        $this->command->info('Seeding users...');

        // Create 50 students
        for ($i = 1; $i <= 50; $i++) {
            User::create([
                'username' => "student{$i}",
                'fullname' => "Student Name {$i}",
                'email' => "student{$i}@example.com",
                'password' => Hash::make('password'),
                'birthdate' => Carbon::now()->subYears(rand(18, 30))->subDays(rand(0, 365)),
                'gender' => $this->randomGender(),
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 5 admins
        for ($i = 1; $i <= 5; $i++) {
            User::create([
                'username' => "admin{$i}",
                'fullname' => "Admin Name {$i}",
                'email' => "admin{$i}@example.com",
                'password' => Hash::make('password'),
                'birthdate' => Carbon::now()->subYears(rand(25, 40))->subDays(rand(0, 365)),
                'gender' => $this->randomGender(),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create 10 instructors
        for ($i = 1; $i <= 10; $i++) {
            User::create([
                'username' => "instructor{$i}",
                'fullname' => "Instructor Name {$i}",
                'email' => "instructor{$i}@example.com",
                'password' => Hash::make('password'),
                'birthdate' => Carbon::now()->subYears(rand(25, 50))->subDays(rand(0, 365)),
                'gender' => $this->randomGender(),
                'role' => 'instructor',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Users seeded successfully!');
    }

    private function randomGender()
    {
        return collect(['Male', 'Female', 'Other'])->random();
    }

    private function randomStatus()
    {
        return collect(['active', 'suspended'])->random();
    }

    private function seedAdmins()
    {
        $this->command->info('Seeding admins...');

        $adminUsers = User::where('role', 'admin')->get();
        foreach ($adminUsers as $adminUser) {
            Admins::create([
                'user_id' => $adminUser->id,
                'admin_level' => collect(['organization', 'program'])->random(),
                'activity_log' => "Initial setup for admin {$adminUser->username}",
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
        $organizations = ['Tech Academy', 'Learning Platform', 'Code Institute', 'AI Academy', 'Web Dev School'];

        foreach ($instructorUsers as $index => $instructorUser) {
            Instructors::create([
                'user_id' => $instructorUser->id,
                'bio' => "Expert in " . collect(['programming', 'web development', 'data science', 'AI', 'machine learning'])->random() . " with {$index} years of experience.",
                'organization' => $organizations[array_rand($organizations)],
                'email_paypal' => 'sb-iqclf44276453@personal.example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Instructors seeded successfully!');
    }

    private function seedStudents()
    {
        $this->command->info('Seeding students...');

        $studentUsers = User::where('role', 'student')->get();
        $levels = ['Beginner', 'Intermediate', 'Advanced', 'Unknown'];
        $goals = ['Skill development', 'Career advancement', 'Personal interest', 'Certification'];

        foreach ($studentUsers as $studentUser) {
            Student::create([
                'user_id' => $studentUser->id,
                'LoE_DI' => $levels[array_rand($levels)],
                'learning_goals' => $goals[array_rand($goals)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Students seeded successfully!');
    }
 private function seedCategories()
{
    $this->command->info('Seeding categories...');

    // Đường dẫn đến file CSV
    $csvFile = public_path('english_courses.csv');
    if (!File::exists($csvFile)) {
        $this->command->error('CSV file not found for categories seeding!');
        return;
    }

    // Đọc file CSV để lấy danh sách subject duy nhất
    $file = fopen($csvFile, 'r');
    $header = fgetcsv($file); // Bỏ qua dòng tiêu đề
    $subjects = [];

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($header, $row);
        $subject = trim($data['subject']);
        if (!empty($subject) && !in_array($subject, $subjects)) {
            $subjects[] = $subject;
        }
    }
    fclose($file);

    // Tạo danh mục cha
    $parent = Category::create([
        'name' => 'Programming',
        'parent_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Tạo các danh mục con từ subjects
    foreach ($subjects as $subject) {
        Category::create([
            'name' => $subject,
            'parent_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->command->info('Categories seeded successfully!');
}

    // private function seedCourses()
    // {
    //     $this->command->info('Seeding courses from CSV...');

    //     $instructors = Instructors::all()->pluck('id')->toArray();
    //     $csvFile = public_path('english_courses.csv');
    //     if (!File::exists($csvFile)) {
    //         $this->command->error('CSV file not found!');
    //         return;
    //     }

    //     $file = fopen($csvFile, 'r');
    //     $header = fgetcsv($file);
    //     $skippedCourses = 0;

    //     while (($row = fgetcsv($file)) !== false) {
    //         $data = array_combine($header, $row);

    //         if (Course::where('course_name', $data['course_title'])->exists()) {
    //             $this->command->warn("Skipping course '{$data['course_title']}' due to duplicate course_name.");
    //             $skippedCourses++;
    //             continue;
    //         }

    //         $level = match ($data['level']) {
    //             'All Levels', 'Beginner Level' => 'Beginner',
    //             'Intermediate Level' => 'Intermediate',
    //             'Expert Level', 'Advanced Level' => 'Advanced',
    //             default => null,
    //         };

    //         $price = $data['is_paid'] === 'True' ? floatval(str_replace('$', '', $data['price'])) * 23000 : 0; // Convert USD to VND
    //         $instructorId = $instructors[array_rand($instructors)];

    //         $course = Course::create([
    //             'instructor_id' => $instructorId,
    //             'course_name' => $data['course_title'],
    //             'difficulty_level' => $level,
    //             'course_rating' => 0,
    //             'course_url' => $data['url'],
    //             'image' => "https://res.cloudinary.com/dj11e209p/image/upload/v1751878057/How-to-Create-an-Online-Course-For-Free--Complete-Guide--6_ulvjwh.jpg",
    //             'course_description' => "Learn {$data['subject']} with practical examples and hands-on projects.",
    //             'price' => $price,
    //             'skills' => $data['subject'],
    //             'status' => collect(['pending', 'approved', 'draft'])->random(),
    //             'is_certificate_enabled' => rand(0, 1),
    //             'created_at' => $data['published_timestamp'],
    //             'updated_at' => $data['published_timestamp'],
    //         ]);

    //         $category = Category::where('name', $data['subject'])->first();
    //         if ($category) {
    //             CourseCategory::create([
    //                 'course_id' => $course->id,
    //                 'category_id' => $category->id,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);
    //         }
    //     }

    //     fclose($file);
    //     $this->command->info("Courses seeded successfully from CSV! Skipped $skippedCourses duplicate courses.");
    // }
    private function seedCourses()
{
    $this->command->info('Seeding courses from CSV...');

    $instructors = Instructors::all()->pluck('id')->toArray();
    $csvFile = public_path('english_courses.csv');
    if (!File::exists($csvFile)) {
        $this->command->error('CSV file not found!');
        return;
    }

    $file = fopen($csvFile, 'r');
    $header = fgetcsv($file);
    $skippedCourses = 0;
    $courseCount = 0;

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($header, $row);

        if (Course::where('course_name', $data['course_title'])->exists()) {
            $this->command->warn("Skipping course '{$data['course_title']}' due to duplicate course_name.");
            $skippedCourses++;
            continue;
        }

        $level = match ($data['level']) {
            'All Levels', 'Beginner Level' => 'Beginner',
            'Intermediate Level' => 'Intermediate',
            'Expert Level', 'Advanced Level' => 'Advanced',
            default => null,
        };

        $price = $data['is_paid'] === 'True' ? floatval(str_replace('$', '', $data['price'])) : 0;
        $instructorId = $instructors[array_rand($instructors)];

        // Xác định status
        $status = $courseCount < 200 ? 'approved' : collect(['pending', 'approved', 'draft'])->random();

        $course = Course::create([
            'instructor_id' => $instructorId,
            'course_name' => $data['course_title'],
            'difficulty_level' => $level,
            'course_rating' => 0,
            'course_url' => $data['url'],
            'image' => "https://res.cloudinary.com/dj11e209p/image/upload/v1751878057/How-to-Create-an-Online-Course-For-Free--Complete-Guide--6_ulvjwh.jpg",
            'course_description' => "Learn {$data['subject']} with practical examples and hands-on projects.",
            'price' => rand(1, 200),
            'skills' => $data['subject'],
            'status' => $status,
            'is_certificate_enabled' => rand(0, 1),
            'created_at' => $data['published_timestamp'],
            'updated_at' => $data['published_timestamp'],
        ]);

        $courseCount++;

        $category = Category::where('name', $data['subject'])->first();
        if ($category) {
            CourseCategory::create([
                'course_id' => $course->id,
                'category_id' => $category->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    fclose($file);
    $this->command->info("Courses seeded successfully from CSV! Skipped $skippedCourses duplicate courses.");
}


    private function seedStudentCategories()
    {
        $this->command->info('Seeding student categories...');

        $students = Student::all();
        $categories = Category::whereNull('parent_id')->get()->pluck('id')->toArray();

        foreach ($students as $student) {
            $numCategories = rand(1, 3);
            $selectedCategories = array_slice($categories, 0, $numCategories);

            foreach ($selectedCategories as $categoryId) {
                StudentCategory::create([
                    'student_id' => $student->id,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Student categories seeded successfully!');
    }

    private function seedEnrollments()
    {
        $this->command->info('Seeding enrollments...');

        $students = User::where('role', 'student')->get();
        $courses = Course::has('lessons', '>', 1)->pluck('id')->toArray();

        foreach ($students as $student) {
            $numEnrollments = rand(1, 5);
            $selectedCourses = array_slice($courses, 0, $numEnrollments);

            foreach ($selectedCourses as $courseId) {
                Enrollment::create([
                    'user_id' => $student->id,
                    'course_id' => $courseId,
                    'enrolled_at' => now()->subDays(rand(1, 30)),
                    'completed_at' => rand(0, 1) ? now()->subDays(rand(1, 10)) : null,
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

        $enrollments = Enrollment::where('completed_at', '!=',null)->get();
        foreach ($enrollments as $enrollment) {
            $course = Course::find($enrollment->course_id);
            if ($course->is_certificate_enabled) {
                Certificate::create([
                    'enrollment_id' => $enrollment->id,
                    'certificate_code' => 'CERT-' . Str::random(10),
                    'issued_at' => now()->subDays(rand(1, 5)),
                    'download_url' => "certificates/cert_" . Str::random(10) . ".pdf",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Certificates seeded successfully!');
    }

    private function seedCertificateRules()
    {
        $this->command->info('Seeding certificate rules...');

        $courses = Course::where('is_certificate_enabled', 1)->get();
        foreach ($courses as $course) {
            CertificateRule::create([
                'course_id' => $course->id,
                'lesson_completion_percent' => rand(70, 100),
                'lesson_version_rule' => collect(['latest', 'any'])->random(),
                'quiz_min_score' => rand(60, 80),
                'quiz_version_rule' => collect(['latest', 'any'])->random(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Certificate rules seeded successfully!');
    }

    private function seedCoupons()
    {
        $this->command->info('Seeding coupons...');

        $courses = Course::all()->pluck('id')->toArray();
        $couponData = [
            ['code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => 10, 'min_order' => 10],
            ['code' => 'FIXED5000', 'discount_type' => 'fixed', 'discount_value' => 15, 'min_order' => 20],
            ['code' => 'WELCOME20', 'discount_type' => 'percent', 'discount_value' => 5, 'min_order' => 15],
            ['code' => 'FREECOURSE', 'discount_type' => 'fixed', 'discount_value' => 2, 'min_order' => 10],
        ];

        foreach ($couponData as $data) {
            Coupon::create([
                'course_id' => $courses[array_rand($courses)],
                'code' => $data['code'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'min_order' => $data['min_order'],
                'start_date' => now()->subDays(rand(10, 30)),
                'end_date' => now()->addDays(rand(30, 90)),
                'usage_limit' => rand(50, 200),
                'used_count' => rand(0, 50),
                'is_active' => rand(0, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Coupons seeded successfully!');
    }

    private function seedPayments()
    {
        $this->command->info('Seeding payments...');

        $enrollments = Enrollment::all();
        $coupons = Coupon::all()->pluck('id')->toArray();

        foreach ($enrollments as $enrollment) {
            $course = Course::find($enrollment->course_id);
            Payment::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'amount' => $course->price,
                'method' => collect(['vnpay', 'paypal'])->random(),
                'transaction_code' => 'TXN-' . Str::random(10),
                'coupon_id' => rand(0, 1) ? $coupons[array_rand($coupons)] : null,
                'status' => collect(['pending', 'completed', 'failed'])->random(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Payments seeded successfully!');
    }

    private function seedAuditLogs()
    {
        $this->command->info('Seeding audit logs...');

        $payments = Payment::all();
        $actions = ['created', 'status_updated', 'refunded'];

        foreach ($payments as $payment) {
            AuditLog::create([
                'payment_id' => $payment->id,
                'action' => $actions[array_rand($actions)],
                'details' => "Payment {$payment->id} action performed",
                'user_id' => $payment->user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Audit logs seeded successfully!');
    }

    private function seedRevenueSessions()
    {
        $this->command->info('Seeding revenue sessions...');

        $months = [1, 2, 3, 4, 5, 6];
        foreach ($months as $month) {
            RevenueSession::create([
                'month' => $month,
                'year' => 2025,
                'total_revenue' => rand(100, 1000),
                'status' => collect(['open', 'closed', 'distributed'])->random(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Revenue sessions seeded successfully!');
    }

    private function seedRevenueDistributions()
    {
        $this->command->info('Seeding revenue distributions...');

        $revenueSessions = RevenueSession::all();
        $courses = Course::all();

        foreach ($revenueSessions as $session) {
            foreach ($courses as $course) {
                RevenueDistribution::create([
                    'revenue_session_id' => $session->id,
                    'course_id' => $course->id,
                    'instructor_share' => rand(70000, 700000),
                    'status' => collect(['pending', 'completed', 'failed'])->random(),
                    'transaction_code' => 'TXN-DIST' . Str::random(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Revenue distributions seeded successfully!');
    }

    private function seedLessons()
    {
        $this->command->info('Seeding lessons...');

        $courses = Course::limit(200)->get();
        foreach ($courses as $course) {
            $numLessons = 3;
            for ($i = 1; $i <= $numLessons; $i++) {
                Lesson::create([
                    'course_id' => $course->id,
                    'title' => "Lesson $i: Topic {$course->course_name} $i",
                    'video_url' => "https://res.cloudinary.com/dj11e209p/video/upload/v1751963122/lessons/course_1/hvektrkr9gzf7qivnwiq.mp4",
                    'duration' => rand(5, 30),
                    'is_preview' => $i <= 2,
                    'sort_order' => $i,
                    'version' => 1,
                    'is_visible' => rand(0, 1),
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
        foreach ($enrollments as $enrollment) {
            $lessons = Lesson::where('course_id', $enrollment->course_id)->where('is_visible',true)->get();
            foreach ($lessons as $lesson) {
                LessonProgress::create([
                    'user_id' => $enrollment->user_id,
                    'lesson_id' => $lesson->id,
                    'status' => collect(['not_started', 'in_progress', 'completed'])->random(),
                    'completed_at' => rand(0, 1) ? now()->subDays(rand(1, 10)) : null,
                    'created_at' => now()
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
            $numQuizzes =2;
            for ($i = 1; $i <= $numQuizzes; $i++) {
                Quiz::create([
                    'lesson_id' => $lesson->id,
                    'title' => "Quiz $i for Lesson {$lesson->id}",
                    'max_attempts' => rand(1, 5),
                    'time_limit' => rand(10, 30),
                    'is_visible' => rand(0, 1),
                    'version' => 1,
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
        foreach ($quizzes as $quiz) {
            $numQuestions = 3;
            for ($i = 1; $i <= $numQuestions; $i++) {
                Question::create([
                    'quiz_id' => $quiz->id,
                    'title' => "Question $i for Quiz {$quiz->id}: What is ...?",
                    'question_type' => collect(['multiple_choice', 'true_false'])->random(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Questions seeded successfully!');
    }

    private function seedQuestionChoices()
    {
        $this->command->info('Seeding question choices...');

        $questions = Question::all();
        foreach ($questions as $question) {
            $choiceCount = $question->question_type === 'multiple_choice' ? 4 : 2;
            for ($i = 1; $i <= $choiceCount; $i++) {
                $isCorrect = $i === 1;
                $content = $question->question_type === 'true_false'
                    ? ($i === 1 ? 'True' : 'False')
                    : "Choice $i for Question {$question->id}";
                if ($isCorrect) {
                    $content .= ' (Correct)';
                } else {
                    $content .= ' (Incorrect)';
                }
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'content' => $content,
                    'is_correct' => $isCorrect,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Question choices seeded successfully!');
    }

    private function seedReviews()
    {
        $this->command->info('Seeding reviews...');

        $enrollments = Enrollment::all();
        $feedbackTypes = ['content_quality', 'instructor', 'not_interested'];

        foreach ($enrollments as $enrollment) {
            if (rand(0, 1)) {
                Review::create([
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'rating' => rand(1, 5),
                    'comment' => collect([
                        'Great course, very informative!',
                        'Could be improved with more examples.',
                        'Excellent instructor!',
                        'Not what I expected.',
                    ])->random(),
                    'feedback_type' => $feedbackTypes[array_rand($feedbackTypes)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Reviews seeded successfully!');
    }

    private function seedReports()
    {
        $this->command->info('Seeding reports...');

        $enrollments = Enrollment::all();
        $reportTypes = ['inappropriate_content', 'technical_issue', 'copyright_violation', 'spam', 'other'];

        foreach ($enrollments as $enrollment) {
            if (rand(0, 2) === 0) {
                Report::create([
                    'user_id' => $enrollment->user_id,
                    'course_id' => $enrollment->course_id,
                    'reason' => collect([
                        'Video playback issues',
                        'Inappropriate content detected',
                        'Suspected copyright violation',
                        'Spam content in lessons',
                        'Other issues',
                    ])->random(),
                    'report_type' => $reportTypes[array_rand($reportTypes)],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->command->info('Reports seeded successfully!');
    }
}