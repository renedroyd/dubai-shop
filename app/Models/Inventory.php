<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'stock_key',
        'quantity_on_hand', 'quantity_reserved',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved);
    }
}
