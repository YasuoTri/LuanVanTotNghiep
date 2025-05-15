<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_sessions', function (Blueprint $table) {
            $table->id();
            $table->integer('month')->comment('Tháng (1-12)');
            $table->integer('year')->comment('Năm');
            $table->decimal('total_revenue', 15, 2)->default(0.00)->comment('Tổng doanh thu tháng');
            $table->decimal('admin_share', 15, 2)->default(0.00)->comment('Phần của admin (30%)');
            $table->decimal('instructor_share', 15, 2)->default(0.00)->comment('Phần của instructor (70%)');
            $table->enum('status', ['open', 'closed', 'distributed'])->default('open')->comment('Trạng thái phiên');
            $table->timestamps();

            // Unique constraint
            $table->unique(['month', 'year'], 'unique_month_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_sessions');
    }
};