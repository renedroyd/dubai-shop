<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'inventory_id', 'product_variant_id', 'product_name', 'variant_name',
        'sku', 'quantity', 'unit_price_amount', 'original_unit_price_amount', 'line_total_amount', 'discount_amount', 'unit_cost_amount', 'pos_price_rule_id', 'currency',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_amount' => 'integer', 'original_unit_price_amount' => 'integer', 'line_total_amount' => 'integer', 'discount_amount' => 'integer', 'unit_cost_amount' => 'integer'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function priceRule(): BelongsTo { return $this->belongsTo(PosPriceRule::class, 'pos_price_rule_id'); }
    public function discountEvents(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(PosDiscountEvent::class); }
}
