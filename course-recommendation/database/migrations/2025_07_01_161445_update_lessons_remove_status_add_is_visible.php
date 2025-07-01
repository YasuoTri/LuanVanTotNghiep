<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateLessonsRemoveStatusAddIsVisible extends Migration
{
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Bỏ trường status
            $table->dropColumn('status');
            // Thêm trường is_visible, default true (1)
            $table->boolean('is_visible')->default(true);
        });
    }

    public function down()
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Thêm lại trường status (nếu muốn rollback)
            $table->string('status')->default('pending');
            // Xóa trường is_visible
            $table->dropColumn('is_visible');
        });
    }
}
