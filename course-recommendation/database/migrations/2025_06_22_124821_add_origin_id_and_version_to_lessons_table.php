<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOriginIdAndVersionToLessonsTable extends Migration
{
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedBigInteger('origin_id')->nullable()->after('id');
            $table->integer('version')->default(1)->after('origin_id');

            // Nếu bạn muốn thêm quan hệ khóa ngoại đến chính bảng lessons
            $table->foreign('origin_id')->references('id')->on('lessons')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropForeign(['origin_id']);
            $table->dropColumn(['origin_id', 'version']);
        });
    }
}
