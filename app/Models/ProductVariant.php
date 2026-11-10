<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'sku', 'barcode', 'options', 'price_amount', 'is_active', 'sort_order'];

    protected $touches = ['product'];

    protected static function booted(): void
    {
        static::creating(function (self $variant): void {
            $variant->external_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['options' => 'array', 'price_amount' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'product_variant_id')->whereHas('warehouse', fn ($query) => $query->where('is_primary', true));
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_variant_id');
    }
}
