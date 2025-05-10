<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimilarityMatrix extends Model
{
    protected $table = 'similarity_matrix';
    protected $primaryKey = ['course_id_1', 'course_id_2'];
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['course_id_1', 'course_id_2', 'similarity_score'];

    public function course1()
    {
        return $this->belongsTo(Course::class, 'course_id_1');
    }

    public function course2()
    {
        return $this->belongsTo(Course::class, 'course_id_2');
    }
}