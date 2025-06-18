<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropInteractionsTableAndFinalCcCnameColumn extends Migration
{
    public function up()
    {
        // Xoá bảng interactions nếu tồn tại
        Schema::dropIfExists('interactions');

        // Xoá cột final_cc_cname_DI trong bảng users nếu tồn tại
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'final_cc_cname_DI')) {
                $table->dropColumn('final_cc_cname_DI');
            }
        });
    }

    public function down()
    {
        // Thêm lại cột nếu rollback
        Schema::table('users', function (Blueprint $table) {
            $table->string('final_cc_cname_DI', 100)->default('Unknown');
        });

        // Tạo lại bảng interactions nếu rollback
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->boolean('viewed')->default(false);
            $table->boolean('explored')->default(false);
            $table->boolean('certified')->default(false);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('last_event')->nullable();
            $table->integer('nevents')->default(0);
            $table->integer('ndays_act')->default(0);
            $table->integer('nplay_video')->default(0);
            $table->integer('nforum_posts')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }
}
