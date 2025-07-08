<?php
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AlterReportsStatusEnum extends Migration
{
    public function up(): void
    {
        // Thay enum cũ (có reviewed) bằng enum mới
        DB::statement("ALTER TABLE reports MODIFY COLUMN status ENUM('pending', 'resolve', 'ignore') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Rollback nếu cần khôi phục lại trạng thái cũ
        DB::statement("ALTER TABLE reports MODIFY COLUMN status ENUM('pending', 'resolve', 'ignore', 'reviewed') DEFAULT 'pending'");
    }
}
