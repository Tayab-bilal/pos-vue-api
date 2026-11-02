<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes;

    public const TYPES = ['damage', 'loss', 'expired', 'count_correction', 'other'];

    // These types can only take stock away
    public const DECREASE_ONLY = ['damage', 'loss', 'expired'];

    protected $fillable = [
        'reference_no',
        'adjustment_date',
        'type',
        'reason',
        'notes',
        'value_change',
        'status',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date:Y-m-d',
            'value_change' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }
}