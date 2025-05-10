<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $fillable = ['name'];

    public function courses()
    {
        // Assuming a course can belong to a category (not defined in schema, but possible extension)
        return $this->hasMany(Course::class, 'category_id');
    }
}