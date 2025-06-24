<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            if (Schema::hasColumn('quizzes', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'banned'])->default('practice');
        });
    }
};
