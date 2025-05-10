<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::create('payments', function (Blueprint $table) {
        //     $table->bigIncrements('id');
        //     $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        //     $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
        //     $table->integer('amount')->comment('Số tiền VND');
        //     $table->enum('method', ['momo', 'zalopay', 'bank_transfer'])->comment('Phương thức thanh toán VN');
        //     $table->string('transaction_code', 50)->nullable()->comment('Mã giao dịch ví điện tử');
        //     $table->foreignId('coupon_id')->nullable()->constrained('coupons')->onDelete('set null');
        //     $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
        //     $table->timestamp('payment_date')->nullable();
        //     $table->timestamp('created_at')->useCurrent();
        // });
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->integer('amount')->comment('Số tiền VND');
            $table->enum('method', ['momo', 'zalopay', 'bank_transfer'])->comment('Phương thức thanh toán VN');
            $table->string('transaction_code', 50)->nullable()->comment('Mã giao dịch ví điện tử');
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};