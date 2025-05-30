<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Bỏ các ràng buộc cũ nếu có
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
        });

        // Thêm ràng buộc mới cho bảng instructors
        Schema::table('instructors', function (Blueprint $table) {
            // Khi xóa user, instructor cũng bị xóa (cascade)
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        // Thêm ràng buộc mới cho bảng course_instructors
        Schema::table('course_instructors', function (Blueprint $table) {
            // Không cho phép xóa instructor nếu có course liên quan (restrict)
            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('restrict');
        });
    }

    public function down()
    {
        // Khôi phục ràng buộc cũ trong trường hợp rollback
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('cascade');
        });
    }
};