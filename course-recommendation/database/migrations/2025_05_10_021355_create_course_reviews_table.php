<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->enum('status', ['approved', 'rejected']);
            $table->text('notes')->nullable()->comment('Ghi chú hoặc lý do từ chối');
            $table->timestamp('reviewed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
    }
};