<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosExchange extends Model
{
    protected $fillable = [
        'original_order_id', 'replacement_order_id', 'pos_refund_id', 'processed_by',
        'returned_amount', 'replacement_amount', 'difference_amount', 'currency', 'reason', 'exchanged_at',
    ];

    protected function casts(): array
    {
        return [
            'returned_amount' => 'integer',
            'replacement_amount' => 'integer',
            'difference_amount' => 'integer',
            'exchanged_at' => 'datetime',
        ];
    }

    public function originalOrder(): BelongsTo { return $this->belongsTo(Order::class, 'original_order_id'); }
    public function replacementOrder(): BelongsTo { return $this->belongsTo(Order::class, 'replacement_order_id'); }
    public function refund(): BelongsTo { return $this->belongsTo(PosRefund::class, 'pos_refund_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
