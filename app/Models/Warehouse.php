<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use Illuminate\\Database\\DatabaseManager;
use Illuminate\\Support\\Facades\\DB;
use RuntimeException;

class Warehouse extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'address', 'notes', 'is_primary', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function primary(): self
    {
        $warehouse = static::query()->where('is_primary', true)->first();

        if (! $warehouse) {
            throw new RuntimeException('No existe un almacén principal configurado.');
        }

        return $warehouse;
    }
}
