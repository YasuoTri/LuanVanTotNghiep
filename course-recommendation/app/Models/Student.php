<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;
    protected $table = 'students';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'learning_goals', 'LoE_DI', 'total_courses_completed','created_at', 'updated_at'];
    protected $dates = ['deleted_at'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function categories()
{
    return $this->belongsToMany(Category::class, 'student_category', 'student_id', 'category_id');
}
}