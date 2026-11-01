<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'inventory_id', 'user_id', 'type', 'quantity_delta', 'reason',
        'reference_type', 'reference_id', 'metadata',
    ];

    protected function casts(): array
    {
        return ['quantity_delta' => 'integer', 'metadata' => 'array'];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
