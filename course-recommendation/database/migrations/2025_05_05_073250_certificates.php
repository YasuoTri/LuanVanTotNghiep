<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('certificates', function (Blueprint $table) {
        //     $table->bigIncrements('id');
        //     $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        //     $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
        //     $table->foreignId('enrollment_id')->constrained('enrollments')->onDelete('cascade');
        //     $table->string('certificate_code', 50)->unique();
        //     $table->timestamp('issued_at')->useCurrent();
        //     $table->string('download_url');
        // });
        Schema::create('certificates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->string('certificate_code', 50)->unique();
            $table->timestamp('issued_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->string('download_url');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('enrollment_id')->references('id')->on('enrollments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};