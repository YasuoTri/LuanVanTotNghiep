<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instructors extends Model
{
    protected $table = 'instructors';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'name', 'bio', 'avatar', 'organization'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_instructors', 'instructor_id', 'course_id');
    }
}