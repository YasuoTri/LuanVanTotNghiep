<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCourseInstructorRelationship extends Migration
{
    public function up()
    {
        // Xoá bảng trung gian cũ many-to-many
        Schema::dropIfExists('course_instructors');

        // Thêm cột instructor_id vào bảng courses
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->nullable()->unique()->after('id');

            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        // Xoá khóa ngoại và cột nếu rollback
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropColumn('instructor_id');
        });

        // Khôi phục bảng trung gian nếu rollback
        Schema::create('course_instructors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('instructor_id');

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('instructor_id')->references('id')->on('instructors');
        });
    }
}

