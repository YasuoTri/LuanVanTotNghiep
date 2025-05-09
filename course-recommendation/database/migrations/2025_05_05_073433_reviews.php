<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->tinyInteger('rating')->comment('1-5 sao');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->unique(['user_id', 'course_id'], 'reviews_user_course_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });

        // Tạo trigger
        DB::unprepared('
            CREATE TRIGGER update_course_rating
            AFTER INSERT ON reviews
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
        ');
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_course_rating');
        Schema::dropIfExists('reviews');
    }
};