<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Xóa khóa ngoại và cột trong bảng certificates
        Schema::table('certificates', function (Blueprint $table) {
            // Xóa khóa ngoại trước
            $table->dropForeign(['user_id']);
            $table->dropForeign(['course_id']);
            // Xóa cột
            $table->dropColumn(['user_id', 'course_id']);
        });

        // Xóa khóa ngoại và cột trong bảng user_answers
        Schema::table('user_answers', function (Blueprint $table) {
            // Xóa khóa ngoại trước
            $table->dropForeign(['user_id']);
            // Xóa cột
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Khôi phục cột và khóa ngoại trong bảng certificates
        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('id');
            $table->unsignedBigInteger('course_id')->after('user_id');
            
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
            $table->foreign('course_id')
                  ->references('id')->on('courses')
                  ->onDelete('cascade');
        });

        // Khôi phục cột và khóa ngoại trong bảng user_answers
        Schema::table('user_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->after('id');
            
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }
};