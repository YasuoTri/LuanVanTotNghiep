<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Tạo bảng reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->tinyInteger('rating')->comment('1-5 sao');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            // Thêm chỉ mục và ràng buộc khóa ngoại
            $table->unique(['user_id', 'course_id'], 'reviews_user_course_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });

        // Kiểm tra xem trigger đã tồn tại chưa trước khi tạo
        $triggerExists = DB::select("
            SELECT TRIGGER_NAME
            FROM information_schema.TRIGGERS
            WHERE TRIGGER_NAME = 'update_course_rating'
            AND EVENT_OBJECT_TABLE = 'reviews'
        ");

        if (empty($triggerExists)) {
            DB::unprepared('
                DELIMITER $$
                CREATE TRIGGER update_course_rating
                AFTER INSERT ON reviews
                FOR EACH ROW
                BEGIN
                    UPDATE courses
                    SET course_rating = (
                        SELECT AVG(rating)
                        FROM reviews
                        WHERE course_id = NEW.course_id
                        AND deleted_at IS NULL
                    )
                    WHERE id = NEW.course_id;
                END$$
                DELIMITER ;
            ');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Xóa trigger nếu tồn tại
        DB::unprepared('DROP TRIGGER IF EXISTS update_course_rating');

        // Xóa bảng reviews
        Schema::dropIfExists('reviews');
    }
};