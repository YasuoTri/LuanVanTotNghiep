<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    protected $table = 'quiz_results';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'quiz_id', 'score', 'completed_at'];
    protected $casts = [
        'score' => 'decimal:2',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }
}