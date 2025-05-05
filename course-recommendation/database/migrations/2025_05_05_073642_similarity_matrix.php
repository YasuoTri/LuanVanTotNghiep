<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('similarity_matrix', function (Blueprint $table) {
            $table->foreignId('course_id_1')
                  ->constrained('courses')
                  ->onDelete('cascade')
                  ->name('similarity_matrix_course_id_1_foreign')
                  ->index();
            $table->foreignId('course_id_2')
                  ->constrained('courses')
                  ->onDelete('cascade')
                  ->name('similarity_matrix_course_id_2_foreign')
                  ->index();
            $table->double('similarity_score');
            $table->primary(['course_id_1', 'course_id_2']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similarity_matrix');
    }
};