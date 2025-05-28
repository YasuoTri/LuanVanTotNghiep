<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructors extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'instructors';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'name', 'bio', 'avatar', 'organization'];
    protected $dates = ['deleted_at'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->hasOne(Course_Instructors::class, 'instructor_id')->with('course');
    }
}