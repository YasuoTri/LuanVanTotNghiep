<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TRIGGER check_payment_role
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                IF (SELECT role FROM users WHERE id = NEW.user_id) != 'student' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Only users with role \"student\" can make payments';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS check_payment_role');
    }
};