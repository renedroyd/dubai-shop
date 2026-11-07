<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_id', 'user_id', 'from_location_id', 'to_location_id', 'type', 'quantity_delta', 'reason',
        'reference_type', 'reference_id', 'metadata',
    ];

    protected function casts(): array
    {
        return ['quantity_delta' => 'integer', 'metadata' => 'array'];
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('inventory', fn (Builder $inventory) => $inventory->whereIn('warehouse_id', $user->warehouseIdsWithPermission('view')));
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
