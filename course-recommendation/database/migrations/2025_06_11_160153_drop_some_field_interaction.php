<?php
// File: database/migrations/2025_06_11_160100_remove_rating_nchapters_nforum_posts_from_interactions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropColumn(['rating', 'nchapters']);
        });
    }

    public function down()
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->double('rating')->nullable()->after('course_id');
            $table->integer('nchapters')->default(0)->after('nplay_video');
        });
    }
};