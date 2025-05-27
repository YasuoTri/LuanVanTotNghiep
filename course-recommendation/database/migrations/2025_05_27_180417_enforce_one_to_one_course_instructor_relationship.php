<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Step 1: Clean up duplicate course_id and instructor_id entries
        // Keep the earliest record for each course_id and instructor_id
        DB::statement('
            DELETE FROM course_instructors
            WHERE (course_id, instructor_id) NOT IN (
                SELECT course_id, MIN(instructor_id) as instructor_id
                FROM course_instructors
                GROUP BY course_id
            )
        ');

        DB::statement('
            DELETE FROM course_instructors
            WHERE (course_id, instructor_id) NOT IN (
                SELECT MIN(course_id) as course_id, instructor_id
                FROM course_instructors
                GROUP BY instructor_id
            )
        ');

        // Step 2: Drop existing foreign key constraints
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['instructor_id']);
        });

        // Step 3: Drop the composite primary key
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropPrimary(['course_id', 'instructor_id']);
        });

        // Step 4: Add id column as primary key and unique constraints
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->bigIncrements('id')->first(); // Add id as primary key
            $table->unique('course_id'); // Each course can only appear once
        });

        // Step 5: Re-add foreign key constraints
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');
            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        // Step 1: Drop foreign key constraints
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['instructor_id']);
        });

        // Step 2: Drop unique constraints and id column
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->dropUnique(['course_id']);
            $table->dropUnique(['instructor_id']);
            $table->dropColumn('id');
        });

        // Step 3: Restore composite primary key
        Schema::table('course_instructors', function (Blueprint $table) {
            $table->primary(['course_id', 'instructor_id']);
            $table->foreign('course_id')
                  ->references('id')
                  ->on('courses')
                  ->onDelete('cascade');
            $table->foreign('instructor_id')
                  ->references('id')
                  ->on('instructors')
                  ->onDelete('cascade');
        });
    }
};