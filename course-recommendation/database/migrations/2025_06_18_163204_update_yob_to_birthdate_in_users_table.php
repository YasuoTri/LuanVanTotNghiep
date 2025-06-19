<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateYobToBirthdateInUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Xoá cột YoB nếu có
            if (Schema::hasColumn('users', 'YoB')) {
                $table->dropColumn('YoB');
            }

            // Thêm cột ngày sinh
            $table->date('birthdate')->nullable()->after('LoE_DI');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Rollback lại: xóa birthdate, thêm lại YoB
            $table->dropColumn('birthdate');
            $table->integer('YoB')->nullable()->after('LoE_DI');
        });
    }
}

