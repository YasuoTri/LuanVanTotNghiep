<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $table = 'interactions';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_id', 'course_id', 'rating', 'viewed', 'explored', 'certified',
        'start_time', 'last_event', 'nevents', 'ndays_act', 'nplay_video',
        'nchapters', 'nforum_posts'
    ];
    protected $casts = [
        'viewed' => 'boolean',
        'explored' => 'boolean',
        'certified' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}