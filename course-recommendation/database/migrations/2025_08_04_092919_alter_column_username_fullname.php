<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Giảm độ dài xuống 50 ký tự
            $table->string('username', 50)->change();
            $table->string('fullname', 50)->nullable()->change();
            $table->string('email', 50)->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Phục hồi lại độ dài mặc định ban đầu (255)
            $table->string('username', 255)->change();
            $table->string('fullname', 255)->nullable()->change();
            $table->string('email', 255)->nullable()->change();
        });
    }
};
