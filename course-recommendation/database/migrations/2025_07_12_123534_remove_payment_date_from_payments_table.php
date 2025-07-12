<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Nếu cần rollback thì thêm lại cột này (ví dụ kiểu datetime)
            $table->dateTime('payment_date')->nullable();
        });
    }
};
