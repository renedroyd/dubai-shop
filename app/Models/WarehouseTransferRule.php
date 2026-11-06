<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferRule extends Model
{
    protected $fillable = [
        'name', 'source_warehouse_id', 'destination_warehouse_id',
        'min_quantity', 'max_quantity', 'required_approvals',
        'auto_approve', 'requester_can_approve', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'required_approvals' => 'integer',
            'auto_approve' => 'boolean',
            'requester_can_approve' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
}
