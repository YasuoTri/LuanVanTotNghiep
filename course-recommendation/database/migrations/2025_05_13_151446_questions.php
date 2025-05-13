<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quiz_id');
            $table->text('title');
            $table->enum('question_type', ['multiple_choice', 'true_false', 'open_ended']);
            $table->decimal('points', 5, 2)->default(1.00);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            $table->index('quiz_id', 'questions_quiz_id_foreign');
        });
    }

    public function down()
    {
        Schema::dropIfExists('questions');
    }
};