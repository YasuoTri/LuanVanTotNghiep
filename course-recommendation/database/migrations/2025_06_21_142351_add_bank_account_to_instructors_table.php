<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('bank_account', 50)
                  ->nullable()
                  ->after('organization')
                  ->comment('Số tài khoản ngân hàng để nhận thanh toán');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('bank_account');
        });
    }
};
