<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('similarity_matrix', function (Blueprint $table) {
        //     $table->foreignId('course_id_1')
        //           ->constrained('courses')
        //           ->onDelete('cascade')
        //           ->name('similarity_matrix_course_id_1_foreign')
        //           ->index();
        //     $table->foreignId('course_id_2')
        //           ->constrained('courses')
        //           ->onDelete('cascade')
        //           ->name('similarity_matrix_course_id_2_foreign')
        //           ->index();
        //     $table->double('similarity_score');
        //     $table->primary(['course_id_1', 'course_id_2']);
        // });
        Schema::create('similarity_matrix', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id_1');
            $table->unsignedBigInteger('course_id_2');
            $table->double('similarity_score');

            $table->primary(['course_id_1', 'course_id_2']);
            $table->index('course_id_1', 'idx_similarity_course_id_1');
            $table->index('course_id_2', 'idx_similarity_course_id_2');
            $table->foreign('course_id_1')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('course_id_2')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similarity_matrix');
    }
};