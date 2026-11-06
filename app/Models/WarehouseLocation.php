<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WarehouseLocation extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $location): void {
            if ($location->parent_id === null) {
                return;
            }

            if ((int) $location->parent_id === (int) $location->getKey()) {
                throw ValidationException::withMessages([
                    'parent_id' => __('Una ubicación no puede ser su propia ubicación superior.'),
                ]);
            }

            $parent = self::query()->find($location->parent_id);

            if (! $parent || (int) $parent->warehouse_id !== (int) $location->warehouse_id) {
                throw ValidationException::withMessages([
                    'parent_id' => __('La ubicación superior debe pertenecer al mismo almacén.'),
                ]);
            }

            $ancestor = $parent;

            while ($ancestor) {
                if ((int) $ancestor->getKey() === (int) $location->getKey()) {
                    throw ValidationException::withMessages([
                        'parent_id' => __('No se puede crear un ciclo en la jerarquía de ubicaciones.'),
                    ]);
                }

                $ancestor = $ancestor->parent;
            }
        });
    }
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

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryLocationBalance::class, 'warehouse_location_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }
}
