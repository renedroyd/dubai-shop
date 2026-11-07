<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferItem extends Model
{
    protected $fillable = [
        'warehouse_transfer_id', 'source_inventory_id', 'destination_inventory_id',
        'source_location_id', 'destination_location_id', 'quantity_requested',
        'quantity_dispatched', 'quantity_received', 'quantity_damaged', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_dispatched' => 'integer',
            'quantity_received' => 'integer',
            'quantity_damaged' => 'integer',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function sourceInventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'source_inventory_id');
    }

    public function destinationInventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'destination_inventory_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'destination_location_id');
    }
}
