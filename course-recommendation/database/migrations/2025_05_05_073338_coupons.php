<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique()->comment('Mã giảm giá');
            $table->enum('discount_type', ['percent', 'fixed']);
            $table->integer('discount_value')->comment('10 = 10% hoặc 10.000đ');
            $table->integer('min_order')->nullable()->comment('Đơn tối thiểu');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('usage_limit')->nullable()->comment('Số lần dùng tối đa');
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};