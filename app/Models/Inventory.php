<?php

namespace App\Models;

use App\Models\Concerns\TransactionalModelWrites;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Inventory extends Model
{
    use TransactionalModelWrites;

    protected $fillable = [
        'warehouse_id', 'product_id', 'product_variant_id', 'stock_key',
        'quantity_on_hand', 'quantity_reserved', 'quantity_blocked', 'minimum_stock_quantity', 'acquisition_cost_amount', 'minimum_sale_price_amount',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $inventory): void {
            $productId = $inventory->product_id
                ?? ProductVariant::query()->whereKey($inventory->product_variant_id)->value('product_id');

            self::bumpProductRevision($productId);
        });

        static::deleted(function (self $inventory): void {
            $productId = $inventory->product_id
                ?? ProductVariant::query()->whereKey($inventory->product_variant_id)->value('product_id');

            self::bumpProductRevision($productId);
        });
    }

    protected static function bumpProductRevision(?int $productId): void
    {
        if (! $productId) {
            return;
        }

        DB::table('products')->where('id', $productId)->update([
            'external_revision' => DB::raw('external_revision + 1'),
            'updated_at' => now(),
        ]);
    }

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_blocked' => 'integer',
            'minimum_stock_quantity' => 'integer',
            'acquisition_cost_amount' => 'integer',
            'minimum_sale_price_amount' => 'integer',
        ];
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('warehouse_id', $user->warehouseIdsWithPermission('view'));
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function locationBalances(): HasMany
    {
        return $this->hasMany(InventoryLocationBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function costChanges(): HasMany
    {
        return $this->hasMany(InventoryCostChange::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved - $this->quantity_blocked);
    }
}
