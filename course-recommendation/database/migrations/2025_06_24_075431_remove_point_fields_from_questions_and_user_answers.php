<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'point')) {
                $table->dropColumn('point');
            }
        });

        Schema::table('user_answers', function (Blueprint $table) {
            if (Schema::hasColumn('user_answers', 'points_earned')) {
                $table->dropColumn('points_earned');
            }
            if (Schema::hasColumn('user_answers', 'answer_text')) {
                $table->dropColumn('answer_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedInteger('point')->default(1);
        });

        Schema::table('user_answers', function (Blueprint $table) {
            $table->unsignedInteger('points_earned')->default(0);
            $table->text('answer_text')->nullable();
        });
    }
};
