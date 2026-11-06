<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransferEvent extends Model
{
    protected $fillable = [
        'warehouse_transfer_id', 'user_id', 'event', 'from_status', 'to_status', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(WarehouseTransfer::class, 'warehouse_transfer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
