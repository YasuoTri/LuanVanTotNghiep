<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->tinyInteger('rating')->comment('1-5 sao');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'course_id'], 'reviews_user_course_unique');
        });

        // Tạo trigger để cập nhật course_rating
        DB::statement("
            CREATE TRIGGER update_course_rating AFTER INSERT ON reviews
            FOR EACH ROW
            BEGIN
                UPDATE courses 
                SET course_rating = (
                    SELECT AVG(rating) 
                    FROM reviews 
                    WHERE course_id = NEW.course_id
                )
                WHERE id = NEW.course_id;
            END
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS update_course_rating');
        Schema::dropIfExists('reviews');
    }
};