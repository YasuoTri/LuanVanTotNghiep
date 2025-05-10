<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('lesson_progress', function (Blueprint $table) {
        //     $table->bigIncrements('id');
        //     $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        //     $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
        //     $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
        //     $table->timestamp('completed_at')->nullable();
        //     $table->timestamps();
        // });
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('lesson_id');
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lesson_id')->references('id')->on('lessons')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};