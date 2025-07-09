<?php

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\CheckInstructorRole;
use App\Http\Middleware\CheckStudentOrInstructor;
use App\Http\Middleware\InstructorOrAdmin;
use App\Http\Middleware\StudentOrAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckStudentRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Session\Middleware\StartSession;
use App\Console\Kernel as ConsoleKernel;
use App\Http\Middleware\Cors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Bạn cũng có thể định nghĩa scheduler trực tiếp ở đây
        $schedule->job(new \App\Jobs\CreateRevenueSessionJob)->monthlyOn(1, '00:00');
        $schedule->job(new \App\Jobs\DistributeRevenueJob)->monthlyOn(28, '23:59')->when(function () {
            return now()->endOfMonth()->isToday();
        });
    })
    ->withMiddleware(function (Middleware $middleware) {
        // Đăng ký middleware alias
        $middleware->alias([
            'student' => CheckStudentRole::class,
            'admin' => CheckAdminRole::class,
            'instructor' => CheckInstructorRole::class,
            'instructor_or_admin' => InstructorOrAdmin::class,
            'student_or_admin' => StudentOrAdmin::class,
            'instructor_or_student' => CheckStudentOrInstructor::class,
            'jwt_cookie' => \App\Http\Middleware\JwtCookieMiddleware::class,
            'EnsureUserHasRole' => \App\Http\Middleware\EnsureUserHasRole::class,
            'all_user'=> App\Http\Middleware\AllUser::class,

            // 'cors' => Cors::class,
        ]);

        // (Tùy chọn) Áp dụng middleware cho các route hoặc group
        // Ví dụ: Áp dụng middleware 'student' cho một group route
        $middleware->web(append: [
            // StartSession::class,
            
        ]);

        $middleware->api(prepend: [
            // Thêm middleware mặc định cho route API nếu cần
            // Cors::class,
        ]);
        $middleware->encryptCookies(except: [
        'jwt_token',
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();