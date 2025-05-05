<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'course_id',
        'rating',
        'viewed',
        'explored',
        'certified',
        'start_time',
        'last_event',
        'nevents',
        'ndays_act',
        'nplay_video',
        'nchapters',
        'nforum_posts',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rating' => 'float',
        'viewed' => 'boolean',
        'explored' => 'boolean',
        'certified' => 'boolean',
        'start_time' => 'datetime',
        'last_event' => 'datetime',
        'nevents' => 'integer',
        'ndays_act' => 'integer',
        'nplay_video' => 'integer',
        'nchapters' => 'integer',
        'nforum_posts' => 'integer',
    ];

    /**
     * Get the user that owns the interaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course that owns the interaction.
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
