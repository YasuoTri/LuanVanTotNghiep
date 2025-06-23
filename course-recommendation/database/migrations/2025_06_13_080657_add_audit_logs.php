// database/migrations/2025_06_13_080000_create_audit_logs_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->text('action')->comment('Hành động: created, status_updated, refunded, etc.');
            $table->text('details')->nullable()->comment('Chi tiết hành động');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Người thực hiện hành động');
            $table->timestamps();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};