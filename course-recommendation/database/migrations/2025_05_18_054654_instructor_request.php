<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('phone_number', 20)->nullable()->comment('Số điện thoại liên hệ');
            $table->text('professional_links')->nullable()->comment('Link LinkedIn, portfolio, v.v.');
            $table->text('bio')->comment('Tiểu sử chuyên nghiệp');
            $table->string('organization', 100)->nullable()->comment('Tổ chức/cơ quan làm việc');
            $table->text('qualifications')->comment('Trình độ học vấn, chứng chỉ, kinh nghiệm');
            $table->text('teaching_experience')->nullable()->comment('Kinh nghiệm giảng dạy');
            $table->text('expertise')->nullable()->comment('Lĩnh vực chuyên môn');
            $table->text('course_proposal')->nullable()->comment('Đề xuất khóa học');
            $table->text('motivation')->nullable()->comment('Lý do muốn trở thành instructor');
            $table->text('document_urls')->nullable()->comment('Link đến tài liệu bổ sung');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable()->comment('Ghi chú từ admin');
            $table->timestamp('reviewed_at')->nullable();
            // $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null');          
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_requests');
    }
};