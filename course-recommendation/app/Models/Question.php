<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id', 'title', 'question_type', 'points', 'sort_order', 'is_visible',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function choices()
    {
        return $this->hasMany(QuestionChoice::class);
    }

    public function userAnswers()
    {
        return $this->hasMany(UserAnswer::class);
    }
}