<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->json('snapshot_json')->nullable()->after('score')->comment('Lưu cấu trúc quiz tại thời điểm học viên làm bài');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn('snapshot_json');
        });
    }
};
