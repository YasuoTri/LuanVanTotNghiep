<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVersionAndOriginToQuizzesTable extends Migration
{
    public function up()
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedBigInteger('origin_id')->nullable()->after('id');
            $table->unsignedInteger('version')->default(1)->after('origin_id');

            $table->foreign('origin_id')->references('id')->on('quizzes')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropForeign(['origin_id']);
            $table->dropColumn(['origin_id', 'version']);
        });
    }
}
