<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RevenueSession extends Model
{
    /**
     * Các trường có thể gán giá trị hàng loạt.
     *
     * @var array
     */
    protected $fillable = [
        'month',
        'year',
        'total_revenue',
        'status',
    ];

    /**
     * Quan hệ: Một RevenueSession có nhiều Payment.
     *
     * @return HasMany
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'revenue_session_id');
    }

    /**
     * Quan hệ: Một RevenueSession có nhiều RevenueDistribution.
     *
     * @return HasMany
     */
    public function revenueDistributions(): HasMany
    {
        return $this->hasMany(RevenueDistribution::class, 'revenue_session_id');
    }
}