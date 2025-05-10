<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseReview extends Model
{
    protected $fillable = ['course_id', 'admin_id', 'status', 'notes'];
}