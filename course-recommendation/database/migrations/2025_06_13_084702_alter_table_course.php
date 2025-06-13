// database/migrations/2025_06_13_080002_drop_university_from_courses.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('university');
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('university', 255)->nullable()->after('course_name');
        });
    }
};