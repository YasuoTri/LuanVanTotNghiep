<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddTriggerCheckTrueFalseCorrectAnswer extends Migration
{
    public function up()
    {
        DB::unprepared("
            CREATE TRIGGER check_true_false_correct_answer
            BEFORE INSERT ON question_choices
            FOR EACH ROW
            BEGIN
                DECLARE q_type VARCHAR(50);

                SELECT question_type INTO q_type
                FROM questions
                WHERE id = NEW.question_id;

                IF q_type = 'true_false' AND NEW.is_correct = 1 THEN
                    IF (
                        SELECT COUNT(*) FROM question_choices
                        WHERE question_id = NEW.question_id AND is_correct = 1
                    ) >= 1 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'True/False questions can only have one correct answer';
                    END IF;
                END IF;
            END
        ");
    }

    public function down()
    {
        DB::unprepared("DROP TRIGGER IF EXISTS check_true_false_correct_answer");
    }
}
