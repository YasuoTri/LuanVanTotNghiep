<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('lessons', function (Blueprint $table) {
        //     $table->bigIncrements('id');
        //     $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
        //     $table->string('title');
        //     $table->string('video_url')->nullable();
        //     $table->integer('duration')->nullable()->comment('Thời lượng phút');
        //     $table->boolean('is_preview')->default(false)->comment('Cho xem thử');
        //     $table->integer('sort_order')->default(0);
        //     $table->timestamp('created_at')->useCurrent();
        // });
        Schema::create('lessons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('course_id');
            $table->string('title');
            $table->string('video_url')->nullable();
            $table->integer('duration')->nullable()->comment('Thời lượng phút');
            $table->boolean('is_preview')->default(false)->comment('Cho xem thử');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};