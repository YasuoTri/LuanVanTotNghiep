<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $table = 'certificates';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'course_id',
        'enrollment_id',
        'certificate_code',
        'issued_at',
        'download_url',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
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

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }
}