<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->enum('feedback_type', ['content_quality', 'instructor', 'platform_issue', 'not_interested'])
                  ->nullable()
                  ->default('content_quality')
                  ->collation('utf8mb4_unicode_ci')
                  ->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('feedback_type');
        });
    }
};