<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTagToCoursesTable extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('tag')->nullable()->after('skills');
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
}
