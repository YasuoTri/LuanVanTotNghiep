<?php
// File: database/migrations/2025_06_11_160000_drop_similarity_matrix_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('similarity_matrix');
    }

    public function down()
    {
        Schema::create('similarity_matrix', function ($table) {
            $table->unsignedBigInteger('course_id_1');
            $table->unsignedBigInteger('course_id_2');
            $table->double('similarity_score');
            $table->primary(['course_id_1', 'course_id_2']);
            $table->foreign('course_id_1')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('course_id_2')->references('id')->on('courses')->onDelete('cascade');
            $table->index('course_id_1', 'idx_similarity_course_id_1');
            $table->index('course_id_2', 'idx_similarity_course_id_2');
        });
    }
};