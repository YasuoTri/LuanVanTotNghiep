<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->boolean('flagged')->default(false)->after('content')->comment('Cờ bài viết bị báo cáo');
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn('flagged');
        });
    }
};