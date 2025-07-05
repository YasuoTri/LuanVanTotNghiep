<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up()
{
    Schema::table('reports', function (Blueprint $table) {
        $table->dropForeign(['admin_id']); // xoá ràng buộc
        $table->dropColumn(['status', 'admin_id', 'admin_notes', 'reviewed_at']);
    });
}

public function down()
{
    Schema::table('reports', function (Blueprint $table) {
        $table->enum('status', ['pending','reviewed','resolved'])->default('pending');
        $table->unsignedBigInteger('admin_id')->nullable();
        $table->text('admin_notes')->nullable();
        $table->timestamp('reviewed_at')->nullable();

        $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
    });
}

};
