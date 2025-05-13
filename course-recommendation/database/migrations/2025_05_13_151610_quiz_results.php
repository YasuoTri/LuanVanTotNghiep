<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->integer('attempt_number')->default(1)->comment('Số lần thử bài kiểm tra')->after('quiz_id');
            $table->timestamp('started_at')->nullable()->comment('Thời gian bắt đầu làm bài')->after('attempt_number');
        });
    }

    public function down()
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn(['attempt_number', 'started_at']);
        });
    }
};