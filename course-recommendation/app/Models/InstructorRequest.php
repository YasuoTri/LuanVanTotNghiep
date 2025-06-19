<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstructorRequest extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'professional_links',
        'bio',
        'organization',
        'qualifications',
        'teaching_experience',
        'expertise',
        'course_proposal',
        'motivation',
        'document_urls',
        'status',
        'admin_notes',
        'admin_id',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => 'string',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}