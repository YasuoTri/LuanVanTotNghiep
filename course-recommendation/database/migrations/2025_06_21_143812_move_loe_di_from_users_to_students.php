<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Xóa cột từ bảng users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('LoE_DI');
        });

        // Thêm cột vào bảng students
        Schema::table('students', function (Blueprint $table) {
            $table->string('LoE_DI', 50)->default('Unknown')->after('user_id')
                  ->comment('Trình độ học vấn hoặc thông tin bằng cấp');
        });
    }

    public function down(): void
    {
        // Nếu rollback thì làm ngược lại
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('LoE_DI');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('LoE_DI', 50)->default('Unknown');
        });
    }
};
