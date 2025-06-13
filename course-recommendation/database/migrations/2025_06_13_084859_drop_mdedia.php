<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('media');
    }

    public function down()
    {
        Schema::create('media', function ($table) {
            $table->id();
            $table->string('medially_type');
            $table->unsignedBigInteger('medially_id');
            $table->text('file_url');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['medially_type', 'medially_id']);
        });
    }
};