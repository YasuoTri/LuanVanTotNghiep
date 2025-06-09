<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCoursesTableAddBannedStatus extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Cập nhật cột status để thêm giá trị 'banned'
            $table->enum('status', ['pending', 'approved', 'rejected', 'unavailable', 'banned'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Hoàn tác: Xóa giá trị 'banned' khỏi enum
            $table->enum('status', ['pending', 'approved', 'rejected', 'unavailable'])
                  ->default('pending')
                  ->change();
        });
    }
}