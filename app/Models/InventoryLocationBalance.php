<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLocationBalance extends Model
{
    protected $fillable = [
        'inventory_id',
        'warehouse_location_id',
        'quantity_on_hand',
    ];

    protected function casts(): array
    {
        return ['quantity_on_hand' => 'integer'];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}
