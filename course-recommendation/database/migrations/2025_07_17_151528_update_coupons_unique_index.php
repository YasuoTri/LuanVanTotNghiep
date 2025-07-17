<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCouponsUniqueIndex extends Migration
{
    public function up()
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Bỏ unique trên code đơn lẻ
            $table->dropUnique('coupons_code_unique');

            // Thêm unique composite: trong cùng course không trùng code
            $table->unique(['course_id', 'code'], 'coupons_course_code_unique');
        });
    }

    public function down()
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Bỏ unique composite
            $table->dropUnique('coupons_course_code_unique');

            // Khôi phục unique trên code toàn cục
            $table->unique('code', 'coupons_code_unique');
        });
    }
}
