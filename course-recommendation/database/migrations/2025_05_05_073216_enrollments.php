<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('enrollments', function (Blueprint $table) {
        //     $table->bigIncrements('id');
        //     $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        //     $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
        //     $table->timestamp('enrolled_at')->useCurrent();
        //     $table->timestamp('completed_at')->nullable();
        //     $table->timestamp('expires_at')->nullable();
        //     $table->enum('status', ['active', 'completed'])->default('active');
        //     $table->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');
        // });
        Schema::create('enrollments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->timestamp('enrolled_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'course_id'], 'enrollments_user_course_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};