<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('certificate_rules', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('course_id')->unique(); // mỗi course chỉ có 1 rule
        $table->unsignedBigInteger('instructor_id'); // ai cấu hình
        $table->unsignedTinyInteger('lesson_completion_percent')->default(100); // ví dụ 100% lesson hoàn thành
        $table->enum('lesson_version_rule', ['latest', 'any'])->default('latest'); // có chấp nhận version cũ không
        $table->unsignedTinyInteger('quiz_min_score')->default(60); // tối thiểu 60 điểm
        $table->enum('quiz_version_rule', ['latest', 'any'])->default('latest');
        $table->timestamps();

        $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        $table->foreign('instructor_id')->references('id')->on('instructors')->onDelete('cascade');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_rules');
    }
};
