<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentCategory extends Model
{
    protected $table = 'student_category';
     protected $fillable = [
        'student_id',
        'category_id',
        'created_at',
        'updated_at',
    ];
    public function students()
    {
        return $this->hasMany(Student::class, 'student_category_id', 'student_category_id');

    }
    public function categories()
    {
        return $this->hasMany(Category::class, 'student_category_id', 'student_category_id');
    }
}

