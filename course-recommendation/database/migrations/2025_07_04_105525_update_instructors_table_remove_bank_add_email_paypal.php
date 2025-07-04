<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateInstructorsTableRemoveBankAddEmailPaypal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('instructors', function (Blueprint $table) {
            // Xóa trường bank_account và bank_name
            $table->dropColumn(['bank_account', 'bank_name']);
            
            // Thêm trường email_paypal
            $table->string('email_paypal')->nullable()->comment('Email PayPal để nhận thanh toán')->after('organization');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('instructors', function (Blueprint $table) {
            // Khôi phục trường bank_account và bank_name
            $table->string('bank_account', 50)->nullable()->comment('Số tài khoản ngân hàng để nhận thanh toán')->after('organization');
            $table->string('bank_name', 100)->nullable()->comment('Tên ngân hàng liên kết với tài khoản')->after('bank_account');
            
            // Xóa trường email_paypal
            $table->dropColumn('email_paypal');
        });
    }
}