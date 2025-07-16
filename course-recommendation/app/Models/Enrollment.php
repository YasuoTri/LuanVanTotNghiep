<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Enrollment extends Model
{
    use SoftDeletes;
    protected $table = 'enrollments';
    protected $primaryKey = 'id';
    public $timestamps = false;
    public $incrementing = true;
    protected $fillable = [
        'user_id', 'course_id', 'enrolled_at', 'completed_at'
    ];
    protected $dates = ['deleted_at'];
    protected $casts = [
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class, 'enrollment_id');
    }
}