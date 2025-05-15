<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAccount extends Model
{
    /**
     * Các trường có thể gán giá trị hàng loạt.
     *
     * @var array
     */
    protected $fillable = [
        'admin_id',
        'balance',
        'bank_name',
        'bank_account_number',
    ];

    /**
     * Quan hệ: Một AdminAccount thuộc về một Admin.
     *
     * @return BelongsTo
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admins::class, 'admin_id');
    }
}