<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'learning_goals', 'interests', 'total_courses_completed'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}