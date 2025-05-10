<?php

use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\CheckInstructorRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckStudentRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Đăng ký middleware alias
        $middleware->alias([
            'student' => CheckStudentRole::class,
            'admin' => CheckAdminRole::class,
            'instructor' => CheckInstructorRole::class,
            'instructor_or_admin' => \App\Http\Middleware\InstructorOrAdmin::class,
        ]);

        // (Tùy chọn) Áp dụng middleware cho các route hoặc group
        // Ví dụ: Áp dụng middleware 'student' cho một group route
        $middleware->web(append: [
            // Thêm middleware mặc định cho route web nếu cần
        ]);

        $middleware->api(append: [
            // Thêm middleware mặc định cho route API nếu cần
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();