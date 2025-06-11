<?php
// File: database/migrations/2025_06_11_153600_add_flagged_to_courses_lessons_quizzes_questions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo khóa học')->after('status');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo bài học')->after('status');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo bài kiểm tra')->after('is_visible');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('flagged')->default(0)->comment('Cờ báo cáo câu hỏi')->after('is_visible');
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('flagged');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('flagged');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('flagged');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('flagged');
        });
    }
};