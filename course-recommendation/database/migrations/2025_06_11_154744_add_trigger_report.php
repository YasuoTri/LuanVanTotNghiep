<?php
// File: database/migrations/2025_06_11_154744_add_trigger_report.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    public function up()
    {
        DB::unprepared("
            CREATE TRIGGER `flag_content_on_report` AFTER INSERT ON `reports` FOR EACH ROW
            BEGIN
                IF NEW.reportable_type = 'App\\\\Models\\\\Course' THEN
                    UPDATE courses SET flagged = 1 WHERE id = NEW.reportable_id;
                ELSEIF NEW.reportable_type = 'App\\\\Models\\\\Lesson' THEN
                    UPDATE lessons SET flagged = 1 WHERE id = NEW.reportable_id;
                ELSEIF NEW.reportable_type = 'App\\\\Models\\\\Quiz' THEN
                    UPDATE quizzes SET flagged = 1 WHERE id = NEW.reportable_id;
                ELSEIF NEW.reportable_type = 'App\\\\Models\\\\ForumPost' THEN
                    UPDATE forum_posts SET flagged = 1 WHERE id = NEW.reportable_id;
                ELSEIF NEW.reportable_type = 'App\\\\Models\\\\Question' THEN
                    UPDATE questions SET flagged = 1 WHERE id = NEW.reportable_id;
                END IF;
            END;
        ");
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `flag_content_on_report`');
    }
};