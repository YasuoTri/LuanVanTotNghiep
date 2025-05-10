<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::create('interactions', function (Blueprint $table) {
        //     $table->bigIncrements('id');
        //     $table->unsignedBigInteger('user_id');
        //     $table->unsignedBigInteger('course_id');
        //     $table->float('rating')->nullable();
        //     $table->boolean('viewed')->default(false);
        //     $table->boolean('explored')->default(false);
        //     $table->boolean('certified')->default(false);
        //     $table->timestamp('start_time')->nullable();
        //     $table->timestamp('last_event')->nullable();
        //     $table->integer('nevents')->default(0);
        //     $table->integer('ndays_act')->default(0);
        //     $table->integer('nplay_video')->default(0);
        //     $table->integer('nchapters')->default(0);
        //     $table->integer('nforum_posts')->default(0);
        //     $table->timestamps();
        //     $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        //     $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        // });
        Schema::create('interactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->double('rating')->nullable();
            $table->boolean('viewed')->default(false);
            $table->boolean('explored')->default(false);
            $table->boolean('certified')->default(false);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('last_event')->nullable();
            $table->integer('nevents')->default(0);
            $table->integer('ndays_act')->default(0);
            $table->integer('nplay_video')->default(0);
            $table->integer('nchapters')->default(0);
            $table->integer('nforum_posts')->default(0);
            $table->timestamps();

            $table->index('user_id', 'idx_interactions_user_id');
            $table->index('course_id', 'idx_interactions_course_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
