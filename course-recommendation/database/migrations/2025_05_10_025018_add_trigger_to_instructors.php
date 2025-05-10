<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TRIGGER check_instructor_role
            BEFORE INSERT ON instructors
            FOR EACH ROW
            BEGIN
                IF (SELECT role FROM users WHERE id = NEW.user_id) != 'instructor' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'User must have role \"instructor\" to be added to instructors table';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS check_instructor_role');
    }
};