<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropForumPostsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('forum_posts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('forum_posts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('title');
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'banned'])->default('pending');
            $table->boolean('flagged')->default(false)->comment('Cờ bài viết bị báo cáo');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }
}
