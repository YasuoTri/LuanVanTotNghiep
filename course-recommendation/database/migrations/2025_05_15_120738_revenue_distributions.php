<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('revenue_session_id');
            $table->unsignedBigInteger('instructor_id');
            $table->unsignedBigInteger('course_id');
            $table->decimal('revenue_amount', 15, 2)->comment('Doanh thu từ khóa học của instructor');
            $table->decimal('instructor_share', 15, 2)->comment('Phần của instructor (70%)');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('transaction_code', 50)->nullable()->comment('Mã giao dịch khi chuyển khoản');
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('revenue_session_id')->references('id')->on('revenue_sessions')->onDelete('cascade');
            $table->foreign('instructor_id')->references('id')->on('instructors')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_distributions');
    }
};