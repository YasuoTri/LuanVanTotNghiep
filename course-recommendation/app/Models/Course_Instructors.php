<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course_Instructors extends Model
{
    protected $table = 'course_instructors';
    public $timestamps = false;
    protected $fillable = ['course_id', 'instructor_id'];
    protected $primaryKey = ['course_id', 'instructor_id'];
    public $incrementing = false;

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructors::class, 'instructor_id');
    }
}