<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    protected $fillable = [
        'user_id',
        'report_id',
        'action_taken',
        'admin_notes',
        'suspended_until',
    ];

    protected $casts = [
        'suspended_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}