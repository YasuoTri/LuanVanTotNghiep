<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropInstructorAndAdminAccountsTables extends Migration
{
    public function up()
    {
        Schema::dropIfExists('instructor_accounts');
        Schema::dropIfExists('admin_accounts');
    }

    public function down()
    {
        // Nếu rollback, bạn có thể tạo lại bảng nếu cần
        Schema::create('admin_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Số dư tài khoản admin');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
        });

        Schema::create('instructor_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instructor_id');
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Số dư tài khoản instructor');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->timestamps();

            $table->foreign('instructor_id')->references('id')->on('instructors')->onDelete('cascade');
        });
    }
}
