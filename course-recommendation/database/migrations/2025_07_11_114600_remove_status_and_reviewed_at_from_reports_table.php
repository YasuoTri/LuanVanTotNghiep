<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('reports', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('status', ['pending', 'resolve', 'ignore'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
        });
    }
};
