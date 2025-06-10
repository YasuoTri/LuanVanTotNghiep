<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: drop the unique index on course_name.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_course_name_unique');
        });
    }

    /**
     * Reverse the migrations: add the unique index back on course_name.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unique('course_name');
        });
    }
};
