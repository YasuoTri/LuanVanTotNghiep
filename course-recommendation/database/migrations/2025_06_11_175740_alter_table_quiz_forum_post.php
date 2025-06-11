<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up()
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'banned'])->default('pending')->after('is_visible');
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'banned'])->default('pending')->after('content');
        });
    }

    public function down()
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};