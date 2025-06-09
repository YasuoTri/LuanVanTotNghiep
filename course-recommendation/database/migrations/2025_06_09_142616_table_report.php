<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Người gửi tố cáo (student/instructor)
            $table->unsignedBigInteger('course_id'); // Khóa học bị tố cáo
            $table->text('reason'); // Lý do tố cáo (ví dụ: sao chép nội dung)
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending'); // Trạng thái tố cáo
            $table->unsignedBigInteger('admin_id')->nullable(); // Admin xử lý tố cáo
            $table->text('admin_notes')->nullable(); // Ghi chú từ admin
            $table->timestamp('reviewed_at')->nullable(); // Thời gian admin xử lý
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reports');
    }
};