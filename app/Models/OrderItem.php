<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'inventory_id', 'product_variant_id', 'product_name', 'variant_name',
        'sku', 'quantity', 'unit_price_amount', 'line_total_amount', 'currency',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_amount' => 'integer', 'line_total_amount' => 'integer'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
