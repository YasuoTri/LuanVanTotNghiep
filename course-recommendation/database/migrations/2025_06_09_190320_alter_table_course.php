<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'unavailable', 'banned'])
                  ->default('draft')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'unavailable', 'banned'])
                  ->default('pending')
                  ->change();
        });
    }
};
