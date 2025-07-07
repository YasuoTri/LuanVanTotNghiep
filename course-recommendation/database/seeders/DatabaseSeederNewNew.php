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
use App\Models\ForumPost;
use App\Models\Report;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class DatabaseSeederNewNew extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();
        $this->seedAdmins();
        $this->seedInstructors();
        $this->seedStudents();
        $this->seedCategories();
        $this->seedCourses();
        $this->seedStudentCategories();
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
        $this->seedReviews();
        // $this->seedForumPosts();
        $this->seedReports();
    }

    private function seedUsers()
    {
        $this->command->info('Seeding users...');

        // Create 1 student
        User::create([
            'username' => 'student1',
            'fullname' => 'Student One',
            'email' => 'student1@example.com',
            'password' => Hash::make('password'),
            'birthdate' => '2000-01-01',
            'gender' => 'Male',
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create 1 admin
        User::create([
            'username' => 'admin1',
            'fullname' => 'Admin One',
            'email' => 'admin1@example.com',
            'password' => Hash::make('password'),
            'birthdate' => '1980-01-01',
            'gender' => 'Male',
            'role' => 'admin',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create 2 instructors
        User::create([
            'username' => 'instructor1',
            'fullname' => 'Instructor One',
            'email' => 'instructor1@example.com',
            'password' => Hash::make('password'),
            'birthdate' => '1985-01-01',
            'gender' => 'Female',
            'role' => 'instructor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
            'username' => 'instructor2',
            'fullname'=> 'Instructor Two',
            'email' => 'instructor2@example.com',
            'password' => Hash::make('password'),
            'birthdate' => '1990-01-01',
            'gender' => 'Male',
            'role' => 'instructor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Users seeded successfully!');
    }

    private function seedAdmins()
    {
        $this->command->info('Seeding admins...');

        $adminUser = User::where('role', 'admin')->first();
        Admins::create([
            'user_id' => $adminUser->id,
            'admin_level' => 'organization',
            'activity_log' => 'Initial setup for admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Admins seeded successfully!');
    }

    private function seedInstructors()
    {
        $this->command->info('Seeding instructors...');

        $instructorUsers = User::where('role', 'instructor')->get();
        $instructorData = [
            [
                'user_id' => $instructorUsers[0]->id,
                'bio' => 'Expert in programming and data science.',
                'organization' => 'Learning Platform',
                'email_paypal' => 'sb-iqclf44276453@personal.example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $instructorUsers[1]->id,
                'bio' => 'Specialist in web development and AI.',
                'organization' => 'Tech Academy',
                'email_paypal' => 'sb-iqclf44276454@personal.example.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($instructorData as $data) {
            Instructors::create($data);
        }

        $this->command->info('Instructors seeded successfully!');
    }

    private function seedStudents()
    {
        $this->command->info('Seeding students...');

        $studentUser = User::where('role', 'student')->first();
        Student::create([
            'user_id' => $studentUser->id,
            'LoE_DI' => 'Beginner',
            'learning_goals' => 'Skill development',
            'total_courses_completed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Students seeded successfully!');
    }

  private function seedCategories()
{
    $this->command->info('Seeding categories...');

    // Đường dẫn đến file CSV
    $csvFile = public_path('udemy_coursesReal.csv');
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
            'parent_id' => $parent->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->command->info('Categories seeded successfully!');
}
private function seedCourses()
{
    $this->command->info('Seeding courses from CSV...');

    $instructors = Instructors::all()->pluck('id')->toArray();
    $csvFile = public_path('udemy_coursesReal.csv'); // Đường dẫn đến file CSV
    if (!File::exists($csvFile)) {
        $this->command->error('CSV file not found!');
        return;
    }

    $file = fopen($csvFile, 'r');
    $header = fgetcsv($file); // Bỏ qua dòng tiêu đề

    $skippedCourses = 0; // Đếm số khóa học bị bỏ qua do trùng tên

    while (($row = fgetcsv($file)) !== false) {
        $data = array_combine($header, $row);

        // Kiểm tra xem course_name đã tồn tại chưa
        if (Course::where('course_name', $data['course_title'])->exists()) {
            $this->command->warn("Skipping course '{$data['course_title']}' due to duplicate course_name.");
            $skippedCourses++;
            continue; // Bỏ qua dòng này và chuyển sang dòng tiếp theo
        }

        // Ánh xạ level
        $level = match ($data['level']) {
            'All Levels', 'Beginner Level' => 'Beginner',
            'Intermediate Level' => 'Intermediate',
            'Expert Level', 'Advanced Level' => 'Advanced',
            default => null,
        };

        // Xử lý price
        $price = $data['is_paid'] === 'True' ? floatval(str_replace('$', '', $data['price'])) : 0;

        // Chọn ngẫu nhiên instructor
        $instructorId = $instructors[array_rand($instructors)];

        // Tạo khóa học
        $course = Course::create([
            'instructor_id' => $instructorId,
            'course_name' => $data['course_title'],
            'difficulty_level' => $level,
            'course_rating' => 0, // Sẽ được cập nhật sau khi có review
            'course_url' => $data['url'],
            'image' => null, // Có thể thêm logic để xử lý hình ảnh
            'course_description' =>'Course is about .....', // CSV không có mô tả
            'price' => $price,
            'skills' => $data['subject'], // Sử dụng subject làm skills
            'status' => 'approved',
            'is_certificate_enabled' => 1,
            'created_at' => $data['published_timestamp'],
            'updated_at' => $data['published_timestamp'],
        ]);

        // Gán khóa học vào danh mục tương ứng
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

    // private function seedCourseCategories()
    // {
    //     $this->command->info('Seeding course categories...');

    //     $courses = Course::all();
    //     foreach ($courses as $course) {
    //         $category = Category::where('name', $course->skills)->first();
    //         if ($category) {
    //             CourseCategory::create([
    //                 'course_id' => $course->id,
    //                 'category_id' => $category->id,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);
    //         }
    //     }

    //     $this->command->info('Course categories seeded successfully!');
    // }

    private function seedStudentCategories()
    {
        $this->command->info('Seeding student categories...');

        $student = Student::first();
        $category = Category::where('name', 'Web Development')->first();

        StudentCategory::create([
            'student_id' => $student->id,
            'category_id' => $category->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Student categories seeded successfully!');
    }

    private function seedEnrollments()
    {
        $this->command->info('Seeding 1 enrollment...');

        $student = User::where('role', 'student')->first();
        $course = Course::first();

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'enrolled_at' => now()->subDays(10),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Enrollment seeded successfully!');
    }

    private function seedCertificates()
    {
        $this->command->info('Seeding 1 certificate...');

        $enrollment = Enrollment::first();
        $course = Course::find($enrollment->course_id);

        Certificate::create([
            'user_id' => $enrollment->user_id,
            'instructor_id' => $course->instructor_id,
            'course_id' => $enrollment->course_id,
            'enrollment_id' => $enrollment->id,
            'certificate_code' => 'CERT-1234567890',
            'issued_at' => now(),
            'download_url' => 'certificates/cert_123.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Certificate seeded successfully!');
    }

    private function seedCertificateRules()
    {
        $this->command->info('Seeding certificate rules...');

        $courses = Course::all();

        foreach ($courses as $course) {
            CertificateRule::create([
                'course_id' => $course->id,
                'instructor_id' => $course->instructor_id,
                'lesson_completion_percent' => 80,
                'lesson_version_rule' => 'latest',
                'quiz_min_score' => 70,
                'quiz_version_rule' => 'latest',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Certificate rules seeded successfully!');
    }

    private function seedCoupons()
    {
        $this->command->info('Seeding 1 coupon...');

        Coupon::create([
            'code' => 'SAVE10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order' => 100000,
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(90),
            'usage_limit' => 50,
            'used_count' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Coupon seeded successfully!');
    }

    private function seedPayments()
    {
        $this->command->info('Seeding 1 payment...');

        $enrollment = Enrollment::first();
        $coupon = Coupon::first();

        Payment::create([
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'amount' => 29990,
            'method' => 'vnpay',
            'transaction_code' => 'TXN-1234567890',
            'coupon_id' => $coupon->id,
            'status' => 'completed',
            'payment_date' => now()->subDays(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Payment seeded successfully!');
    }

    private function seedAuditLogs()
    {
        $this->command->info('Seeding 1 audit log...');

        $payment = Payment::first();

        AuditLog::create([
            'payment_id' => $payment->id,
            'action' => 'created',
            'details' => 'Payment created for course enrollment',
            'user_id' => $payment->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Audit log seeded successfully!');
    }

    private function seedRevenueSessions()
    {
        $this->command->info('Seeding 1 revenue session...');

        RevenueSession::create([
            'month' => 7,
            'year' => 2025,
            'total_revenue' => 29990,
            'admin_share' => 8997,
            'instructor_share' => 20993,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Revenue session seeded successfully!');
    }

    private function seedRevenueDistributions()
    {
        $this->command->info('Seeding 1 revenue distribution...');

        $revenueSession = RevenueSession::first();
        $course = Course::first();

        RevenueDistribution::create([
            'revenue_session_id' => $revenueSession->id,
            'instructor_id' => $course->instructor_id,
            'course_id' => $course->id,
            'revenue_amount' => 29990,
            'instructor_share' => 20993,
            'status' => 'pending',
            'transaction_code' => 'TXN-DIST123',
            'distributed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Revenue distribution seeded successfully!');
    }

    private function seedLessons()
    {
        $this->command->info('Seeding lessons...');

        $courses = Course::all();

        foreach ($courses as $course) {
            for ($i = 1; $i <= 3; $i++) {
                Lesson::create([
                    'course_id' => $course->id,
                    'title' => "Lesson $i: Topic $i",
                    'video_url' => "videos/lesson_$i.mp4",
                    'duration' => 10,
                    'is_preview' => $i == 1,
                    'sort_order' => $i,
                    'version' => 1,
                    'is_visible' => true,
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

        $enrollment = Enrollment::first();
        $lessons = Lesson::where('course_id', $enrollment->course_id)->get();

        foreach ($lessons as $lesson) {
            LessonProgress::create([
                'user_id' => $enrollment->user_id,
                'lesson_id' => $lesson->id,
                'status' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Lesson progress seeded successfully!');
    }

    private function seedQuizzes()
    {
        $this->command->info('Seeding quizzes...');

        $lessons = Lesson::all();

        foreach ($lessons as $lesson) {
            for ($i = 1; $i <= 2; $i++) {
                Quiz::create([
                    'lesson_id' => $lesson->id,
                    'title' => "Quiz $i for Lesson {$lesson->id}",
                    'max_attempts' => 3,
                    'time_limit' => 15,
                    'is_visible' => true,
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
            for ($i = 1; $i <= 3; $i++) {
                Question::create([
                    'quiz_id' => $quiz->id,
                    'title' => "Question $i for Quiz {$quiz->id}",
                    'question_type' => $i == 1 ? 'true_false' : 'multiple_choice',
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
                if($isCorrect){
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
        $this->command->info('Seeding 1 review...');

        $enrollment = Enrollment::first();

        Review::create([
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'rating' => 4,
            'comment' => 'Great course!',
            'feedback_type' => 'content_quality',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Review seeded successfully!');
    }

    private function seedForumPosts()
    {
        $this->command->info('Seeding 1 forum post...');

        $enrollment = Enrollment::first();

        ForumPost::create([
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'title' => 'Discussion Topic 1',
            'content' => 'This is a discussion post for the course.',
            'status' => 'approved',
            'flagged' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Forum post seeded successfully!');
    }

    private function seedReports()
    {
        $this->command->info('Seeding 1 report...');

        $enrollment = Enrollment::first();
        $admin = Admins::first();

        Report::create([
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'reason' => 'Technical issue with video playback',
            'report_type' => 'technical_issue',
            'status' => 'pending',
            'admin_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Report seeded successfully!');
    }
}