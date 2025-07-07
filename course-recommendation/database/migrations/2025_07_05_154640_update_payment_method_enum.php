<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY method ENUM('vnpay','paypal') DEFAULT 'paypal'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY method ENUM('vnpay','paypal','momo','zalopay','bank_transfer') DEFAULT 'paypal'");
    }
};
