<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    DB::unprepared("
        CREATE TRIGGER check_certificates_before_disable
        BEFORE UPDATE ON courses
        FOR EACH ROW
        BEGIN
            IF NEW.is_certificate_enabled = 0 THEN
                IF (
                    (SELECT COUNT(*) FROM certificate_rules WHERE course_id = NEW.id) > 0
                    OR
                    (SELECT COUNT(*) FROM certificates WHERE course_id = NEW.id) > 0
                ) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Cannot disable certificate because certificate rules or issued certificates still exist for this course.';
                END IF;
            END IF;
        END
    ");
}

public function down(): void
{
    DB::unprepared("DROP TRIGGER IF EXISTS check_certificates_before_disable");
}
};
