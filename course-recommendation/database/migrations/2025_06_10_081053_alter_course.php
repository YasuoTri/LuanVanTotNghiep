<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Thêm cột origin_id
            $table->unsignedBigInteger('origin_id')->nullable()->after('id');
            $table->foreign('origin_id')->references('id')->on('courses')->onDelete('set null');

            // Thêm cột version
            $table->unsignedInteger('version')->default(1)->after('origin_id');

            // Cập nhật cột status để thêm draft và archived
            $table->enum('status', ['pending', 'approved', 'rejected', 'unavailable', 'banned', 'draft', 'archived'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Xóa foreign key và cột origin_id
            $table->dropForeign(['origin_id']);
            $table->dropColumn('origin_id');

            // Xóa cột version
            $table->dropColumn('version');

            // Khôi phục cột status về trạng thái cũ
            $table->enum('status', ['pending', 'approved', 'rejected', 'unavailable', 'banned'])
                  ->default('pending')
                  ->change();
        });
    }
};