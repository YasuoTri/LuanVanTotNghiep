<?php
// File: database/migrations/2025_06_11_153500_modify_reports_table_for_polymorphic.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tạo bảng reports mới
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->morphs('reportable'); // Tạo reportable_type và reportable_id
            $table->text('reason');
            $table->enum('report_type', [
                'inappropriate_content',
                'technical_issue',
                'copyright_violation',
                'spam',
                'other'
            ])->default('other')->comment('Loại báo cáo');
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    public function down()
    {
        // Khôi phục bảng reports cũ
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->text('reason');
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};