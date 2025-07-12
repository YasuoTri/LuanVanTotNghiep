<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 🗑 Xoá bảng audit_logs nếu tồn tại
        Schema::dropIfExists('audit_logs');

        // 🛠️ Cập nhật cột status trong bảng payments để bỏ 'refunded'
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // 🔁 Khôi phục lại bảng audit_logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->text('action')->comment('Hành động: created, status_updated, refunded, etc.');
            $table->text('details')->nullable()->comment('Chi tiết hành động');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Người thực hiện hành động');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 🔁 Khôi phục lại enum status cũ có 'refunded'
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
    }
};
