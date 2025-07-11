<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_accounts', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Số dư tài khoản admin');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->timestamps();

            // Foreign key
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_accounts');
    }
};