<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueDistribution extends Model
{
    /**
     * Các trường có thể gán giá trị hàng loạt.
     *
     * @var array
     */
    protected $fillable = [
        'revenue_session_id',
        'instructor_id',
        'course_id',
        'revenue_amount',
        'instructor_share',
        'status',
        'transaction_code',
    ];

    /**
     * Quan hệ: Một RevenueDistribution thuộc về một RevenueSession.
     *
     * @return BelongsTo
     */
    public function revenueSession(): BelongsTo
    {
        return $this->belongsTo(RevenueSession::class, 'revenue_session_id');
    }

    /**
     * Quan hệ: Một RevenueDistribution thuộc về một Instructor.
     *
     * @return BelongsTo
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructors::class, 'instructor_id');
    }

    /**
     * Quan hệ: Một RevenueDistribution thuộc về một Course.
     *
     * @return BelongsTo
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}