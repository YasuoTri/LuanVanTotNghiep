<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class QuizResult extends Model
{
    use HasFactory;
    protected $table = 'quiz_results';
    protected $primaryKey = 'id';
protected $fillable = [
        'user_id', 'quiz_id', 'score', 'completed_at', 'attempt_number', 'started_at','snapshot_json',
    ];
    protected $casts = [
        'score' => 'decimal:2',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'started_at' => 'datetime',
        'snapshot_json' => 'json',
        
    ];

public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    public function userAnswers()
    {
        return $this->hasMany(UserAnswer::class);
    }
}