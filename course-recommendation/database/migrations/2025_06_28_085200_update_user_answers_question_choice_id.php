<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('user_answers', function (Blueprint $table) {
        $table->renameColumn('question_index', 'question_id');
        $table->renameColumn('choice_index', 'choice_id');
    });

    Schema::table('user_answers', function (Blueprint $table) {
        $table->foreign('question_id')->references('id')->on('questions')->onDelete('cascade');
        $table->foreign('choice_id')->references('id')->on('question_choices')->onDelete('cascade');
    });
}

public function down(): void
{
    Schema::table('user_answers', function (Blueprint $table) {
        $table->dropForeign(['question_id']);
        $table->dropForeign(['choice_id']);
        $table->renameColumn('question_id', 'question_index');
        $table->renameColumn('choice_id', 'choice_index');
    });
}

};
