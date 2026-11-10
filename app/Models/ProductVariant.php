<?php

namespace App\Models;

use App\Models\Concerns\TransactionalModelWrites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use TransactionalModelWrites;

    protected $fillable = ['product_id', 'name', 'sku', 'barcode', 'options', 'price_amount', 'is_active', 'sort_order'];

    protected static function booted(): void
    {
        static::creating(function (self $variant): void {
            $variant->external_id ??= (string) Str::uuid();
        });

        static::saved(function (self $variant): void {
            DB::table('products')->where('id', $variant->product_id)->update([
                'external_revision' => DB::raw('external_revision + 1'),
                'updated_at' => now(),
            ]);
        });

        static::deleted(function (self $variant): void {
            DB::table('products')->where('id', $variant->product_id)->update([
                'external_revision' => DB::raw('external_revision + 1'),
                'updated_at' => now(),
            ]);
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
