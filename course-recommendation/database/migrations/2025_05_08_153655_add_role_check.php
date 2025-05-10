<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Trigger cho bảng students
        DB::unprepared('
            CREATE TRIGGER check_student_role
            BEFORE INSERT ON students
            FOR EACH ROW
            BEGIN
                IF (SELECT role FROM users WHERE id = NEW.user_id) != "student" THEN
                    SIGNAL SQLSTATE "45000"
                    SET MESSAGE_TEXT = "User must have role \"student\" to be added to students table";
                END IF;
            END
        ');

        // Trigger cho bảng admins
        DB::unprepared('
            CREATE TRIGGER check_admin_role
            BEFORE INSERT ON admins
            FOR EACH ROW
            BEGIN
                IF (SELECT role FROM users WHERE id = NEW.user_id) != "admin" THEN
                    SIGNAL SQLSTATE "45000"
                    SET MESSAGE_TEXT = "User must have role \"admin\" to be added to admins table";
                END IF;
            END
        ');
    }

    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS check_student_role');
        DB::unprepared('DROP TRIGGER IF EXISTS check_admin_role');
    }
};