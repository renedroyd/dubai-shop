<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRefundItem extends Model
{
    protected $fillable = ['pos_refund_id', 'order_item_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'integer'];
    }

    public function refund(): BelongsTo { return $this->belongsTo(PosRefund::class, 'pos_refund_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
}