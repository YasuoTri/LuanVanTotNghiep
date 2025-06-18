<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateReportsTableForCoursesOnly extends Migration
{
    public function up()
    {
        Schema::table('reports', function (Blueprint $table) {
            // Xóa quan hệ đa hình cũ
            $table->dropColumn(['reportable_type', 'reportable_id']);

            // Thêm khoá ngoại đến bảng courses
            $table->unsignedBigInteger('course_id')->after('user_id');

            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('reports', function (Blueprint $table) {
            // Xóa khoá ngoại mới
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');

            // Phục hồi quan hệ đa hình cũ
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');

            $table->index(['reportable_type', 'reportable_id']);
        });
    }
}
