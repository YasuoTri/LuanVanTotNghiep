<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumPost extends Model
{
    protected $table = 'forum_posts';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'course_id', 'title', 'content'];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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