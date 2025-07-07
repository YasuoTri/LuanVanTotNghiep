<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->integer('duration')->nullable(false)->change();
            $table->string('video_url')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->integer('duration')->nullable()->change();
            $table->string('video_url')->nullable()->change();
        });
    }
};
