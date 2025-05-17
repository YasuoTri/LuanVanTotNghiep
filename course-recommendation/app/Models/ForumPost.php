<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ForumPost extends Model
{
    use SoftDeletes;
    protected $table = 'forum_posts';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'course_id', 'title', 'content','flagged', 'created_at', 'updated_at'];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    protected $dates = ['deleted_at'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}