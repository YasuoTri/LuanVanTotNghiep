<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateQuestionTypeEnumInQuestionsTable extends Migration
{
    public function up()
    {
        // Cập nhật cột enum bằng cách dùng raw SQL
        DB::statement("ALTER TABLE `questions` 
            MODIFY `question_type` ENUM('multiple_choice', 'true_false') 
            NOT NULL");
    }

    public function down()
    {
        // Rollback lại enum cũ (có open_ended)
        DB::statement("ALTER TABLE `questions` 
            MODIFY `question_type` ENUM('multiple_choice', 'true_false', 'open_ended') 
            NOT NULL");
    }
}
