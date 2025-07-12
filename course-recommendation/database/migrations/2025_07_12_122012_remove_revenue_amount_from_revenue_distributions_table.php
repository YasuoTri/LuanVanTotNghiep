<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('revenue_distributions', function (Blueprint $table) {
            $table->dropColumn('revenue_amount');
        });
    }

    public function down(): void
    {
        Schema::table('revenue_distributions', function (Blueprint $table) {
            // Nếu bạn muốn rollback thì thêm lại với kiểu phù hợp (ví dụ decimal)
            $table->decimal('revenue_amount', 15, 2)->nullable();
        });
    }
};

