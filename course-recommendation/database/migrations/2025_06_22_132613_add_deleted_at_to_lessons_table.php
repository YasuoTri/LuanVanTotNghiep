<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToLessonsTable extends Migration
{
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->softDeletes(); // tương đương: $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropSoftDeletes(); // tương đương: $table->dropColumn('deleted_at');
        });
    }
}
