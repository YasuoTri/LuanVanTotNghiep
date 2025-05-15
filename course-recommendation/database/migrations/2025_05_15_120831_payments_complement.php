<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('revenue_session_id')->nullable()->after('coupon_id');
            $table->foreign('revenue_session_id')
                  ->references('id')
                  ->on('revenue_sessions') // Sửa lỗi ở đây
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['revenue_session_id']);
            $table->dropColumn('revenue_session_id');
        });
    }
};
