<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use SoftDeletes;
    protected $table = 'certificates';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'instructor_id',
        'enrollment_id',
        'certificate_code',
        'issued_at',
        'download_url',
    ];

    protected $dates = ['deleted_at'];
    protected $casts = [
        'issued_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }

    // public function course()
    // {
    //     return $this->belongsTo(Course::class, 'course_id');
    // }

    public function user()
    {
        // Truy vấn user thông qua enrollment
        return $this->hasOneThrough(
            User::class, // Model đích
            Enrollment::class, // Model trung gian
            'id', // Khóa ngoại trên bảng trung gian (enrollments.id)
            'id', // Khóa chính trên bảng đích (users.id)
            'enrollment_id', // Khóa ngoại trên bảng hiện tại (certificates.enrollment_id)
            'user_id' // Khóa ngoại trên bảng trung gian liên kết với bảng đích (enrollments.user_id)
        );
    }

    public function course()
    {
        // Truy vấn course thông qua enrollment
        return $this->hasOneThrough(
            Course::class, // Model đích
            Enrollment::class, // Model trung gian
            'id', // Khóa ngoại trên bảng trung gian (enrollments.id)
            'id', // Khóa chính trên bảng đích (courses.id)
            'enrollment_id', // Khóa ngoại trên bảng hiện tại (certificates.enrollment_id)
            'course_id' // Khóa ngoại trên bảng trung gian liên kết với bảng đích (enrollments.course_id)
        );
    }
    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }
    public function instructor()
    {
        return $this->belongsTo(Instructors::class, 'instructor_id');
    }
}