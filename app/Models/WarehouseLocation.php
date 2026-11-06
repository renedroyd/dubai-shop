<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class WarehouseLocation extends Model
{
    public const TYPE_AREA = 'area';
    public const TYPE_AISLE = 'aisle';
    public const TYPE_SHELF = 'shelf';
    public const TYPE_BIN = 'bin';
    public const TYPE_UNASSIGNED = 'unassigned';

    protected $fillable = [
        'warehouse_id', 'parent_id', 'code', 'name', 'type', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }
}
