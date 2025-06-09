<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])
                  ->default('draft')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};