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
        Schema::create('courses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('course_name', 255);
            $table->string('university', 255)->nullable();
            $table->string('difficulty_level', 50)->nullable();
            $table->float('course_rating')->default(0);
            $table->text('course_url')->nullable();
            $table->text('course_description')->nullable();
            $table->text('skills')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('skills');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
