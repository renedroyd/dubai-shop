<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseUserAccess extends Model
{
    public const PERMISSIONS = [
        'view',
        'inventory.manage',
        'transfers.request',
        'transfers.approve',
        'pos.operate',
        'reports.view',
        'warehouse.manage',
    ];

    protected $fillable = ['user_id', 'warehouse_id', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
