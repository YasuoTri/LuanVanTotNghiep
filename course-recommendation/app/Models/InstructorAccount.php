<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorAccount extends Model
{
    /**
     * Các trường có thể gán giá trị hàng loạt.
     *
     * @var array
     */
    protected $fillable = [
        'instructor_id',
        'balance',
        'bank_name',
        'bank_account_number',
    ];

    /**
     * Quan hệ: Một InstructorAccount thuộc về một Instructor.
     *
     * @return BelongsTo
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructors::class, 'instructor_id');
    }
}