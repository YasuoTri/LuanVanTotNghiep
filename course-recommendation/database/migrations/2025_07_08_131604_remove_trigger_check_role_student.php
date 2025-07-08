<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the check_student_role trigger
        DB::unprepared('DROP TRIGGER IF EXISTS `check_student_role`');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the check_student_role trigger
        DB::unprepared('
            CREATE TRIGGER `check_student_role` BEFORE INSERT ON `students` FOR EACH ROW
            BEGIN
                IF (SELECT role FROM users WHERE id = NEW.user_id) != "student" THEN
                    SIGNAL SQLSTATE "45000"
                    SET MESSAGE_TEXT = "User must have role \"student\" to be added to students table";
                END IF;
            END
        ');
    }
};