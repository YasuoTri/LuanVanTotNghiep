<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class DropFlagContentOnReportTrigger extends Migration
{
    public function up()
    {
        // Xoá trigger nếu tồn tại
        DB::unprepared('DROP TRIGGER IF EXISTS flag_content_on_report');
    }

    public function down()
    {
        // Có thể để trống hoặc viết lại trigger nếu cần rollback
        // Nếu không rollback trigger thì để trống cũng được
    }
}
