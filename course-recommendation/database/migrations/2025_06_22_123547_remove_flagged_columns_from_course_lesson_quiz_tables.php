<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveFlaggedColumnsFromCourseLessonQuizTables extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'flagged')) {
                $table->dropColumn('flagged');
            }
        });

        Schema::table('lessons', function (Blueprint $table) {
            if (Schema::hasColumn('lessons', 'flagged')) {
                $table->dropColumn('flagged');
            }
        });

        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'flagged')) {
                $table->dropColumn('flagged');
            }
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo khóa học');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo bài học');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo bài kiểm tra');
        });
    }
}
