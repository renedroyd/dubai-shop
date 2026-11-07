<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosDiscountEvent extends Model
{
    protected $fillable = [
        'order_id', 'order_item_id', 'pos_price_rule_id', 'applied_by', 'approved_by',
        'scope', 'discount_amount', 'rule_snapshot',
    ];

    protected function casts(): array
    {
        return ['discount_amount' => 'integer', 'rule_snapshot' => 'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function rule(): BelongsTo { return $this->belongsTo(PosPriceRule::class, 'pos_price_rule_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'applied_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
