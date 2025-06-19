<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Cập nhật bảng course_reviews
        Schema::table('course_reviews', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Cập nhật bảng instructor_requests
        Schema::table('instructor_requests', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
        });

        // Cập nhật bảng reports
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('users')->onDelete('set null');
        });

        // Cập nhật bảng violations
        Schema::table('violations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Xóa bảng admins
        Schema::dropIfExists('admins');
    }

    public function down()
    {
        // Trong down có thể tạo lại bảng admins nếu cần, nhưng tùy yêu cầu có thể bỏ trống
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('admin_level', ['organization', 'program'])->default('program');
            $table->text('activity_log')->nullable();
            $table->timestamps();
        });

        // Gắn lại foreign key cũ
        Schema::table('course_reviews', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
        });

        Schema::table('instructor_requests', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }
};
