<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseTransfer extends Model
{
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'source_warehouse_id', 'destination_warehouse_id', 'requested_by',
        'status', 'required_approvals', 'requested_at', 'approved_at',
        'dispatched_at', 'completed_at', 'rejected_at', 'notes',
        'rejection_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'required_approvals' => 'integer',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'metadata' => 'array',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseTransferItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WarehouseTransferApproval::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WarehouseTransferEvent::class);
    }
}
