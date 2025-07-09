<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateFeedbackTypeEnumOnReviews extends Migration
{
    public function up(): void
    {
        // BƯỚC 1: Cập nhật giá trị cũ "platform_issue" → NULL hoặc giá trị khác
        DB::table('reviews')
            ->where('feedback_type', 'platform_issue')
            ->update(['feedback_type' => null]); // hoặc 'not_interested', tùy ý

        // BƯỚC 2: Thay đổi lại enum
        DB::statement("ALTER TABLE reviews MODIFY COLUMN feedback_type ENUM(
            'content_quality',
            'instructor',
            'not_interested'
        ) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Rollback: khôi phục lại enum cũ (có platform_issue)
        DB::statement("ALTER TABLE reviews MODIFY COLUMN feedback_type ENUM(
            'content_quality',
            'instructor',
            'not_interested',
            'platform_issue'
        ) NULL DEFAULT NULL");
    }
}
