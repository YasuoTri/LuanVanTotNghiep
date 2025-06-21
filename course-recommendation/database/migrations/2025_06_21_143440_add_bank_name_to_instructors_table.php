<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('bank_name', 100)
                  ->nullable()
                  ->after('bank_account')
                  ->comment('Tên ngân hàng liên kết với tài khoản');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('bank_name');
        });
    }
};
