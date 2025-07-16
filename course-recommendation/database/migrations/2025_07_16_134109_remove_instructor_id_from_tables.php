<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoveInstructorIdFromTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Xóa ràng buộc khóa ngoại và cột instructor_id trong bảng certificate_rules
        Schema::table('certificate_rules', function (Blueprint $table) {
            // Kiểm tra tính toàn vẹn dữ liệu trước khi xóa
            $invalidRecords = DB::table('certificate_rules')
                ->join('courses', 'certificate_rules.course_id', '=', 'courses.id')
                ->whereRaw('certificate_rules.instructor_id != courses.instructor_id')
                ->orWhereNull('courses.instructor_id')
                ->count();

            if ($invalidRecords > 0) {
                throw new \Exception('Cannot remove instructor_id from certificate_rules: Some records have mismatched or null instructor_id in courses.');
            }

            // Xóa ràng buộc khóa ngoại
            $table->dropForeign(['instructor_id']);
            // Xóa cột instructor_id
            $table->dropColumn('instructor_id');
        });

        // Xóa ràng buộc khóa ngoại và cột instructor_id trong bảng certificates
        Schema::table('certificates', function (Blueprint $table) {
            // Kiểm tra tính toàn vẹn dữ liệu trước khi xóa
            $invalidRecords = DB::table('certificates')
                ->join('enrollments', 'certificates.enrollment_id', '=', 'enrollments.id')
                ->join('courses', 'enrollments.course_id', '=', 'courses.id')
                ->whereRaw('certificates.instructor_id != courses.instructor_id')
                ->orWhereNull('courses.instructor_id')
                ->count();

            if ($invalidRecords > 0) {
                throw new \Exception('Cannot remove instructor_id from certificates: Some records have mismatched or null instructor_id in courses.');
            }

            // Xóa ràng buộc khóa ngoại
            $table->dropForeign(['instructor_id']);
            // Xóa cột instructor_id
            $table->dropColumn('instructor_id');
        });

        // Xóa ràng buộc khóa ngoại và cột instructor_id trong bảng revenue_distributions
        Schema::table('revenue_distributions', function (Blueprint $table) {
            // Kiểm tra tính toàn vẹn dữ liệu trước khi xóa
            $invalidRecords = DB::table('revenue_distributions')
                ->join('courses', 'revenue_distributions.course_id', '=', 'courses.id')
                ->whereRaw('revenue_distributions.instructor_id != courses.instructor_id')
                ->orWhereNull('courses.instructor_id')
                ->count();

            if ($invalidRecords > 0) {
                throw new \Exception('Cannot remove instructor_id from revenue_distributions: Some records have mismatched or null instructor_id in courses.');
            }

            // Xóa ràng buộc khóa ngoại
            $table->dropForeign(['instructor_id']);
            // Xóa cột instructor_id
            $table->dropColumn('instructor_id');
        });

        // Thêm chỉ mục trên courses.instructor_id để tối ưu hóa truy vấn
        Schema::table('courses', function (Blueprint $table) {
            $table->index('instructor_id', 'idx_courses_instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Khôi phục cột instructor_id và ràng buộc khóa ngoại
        Schema::table('certificate_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->after('course_id');
            $table->foreign('instructor_id', 'certificate_rules_instructor_id_foreign')
                  ->references('id')->on('instructors')->onDelete('cascade');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->nullable()->after('id');
            $table->foreign('instructor_id', 'certificates_instructor_id_foreign')
                  ->references('id')->on('instructors')->onDelete('set null');
        });

        Schema::table('revenue_distributions', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->after('revenue_session_id');
            $table->foreign('instructor_id', 'revenue_distributions_instructor_id_foreign')
                  ->references('id')->on('instructors')->onDelete('cascade');
        });

        // Xóa chỉ mục trên courses.instructor_id
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('idx_courses_instructor_id');
        });
    }
}