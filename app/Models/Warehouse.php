<?php

namespace App\Models;

use App\Models\Concerns\TransactionalModelWrites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class Warehouse extends Model
{
    use TransactionalModelWrites;
    protected static function booted(): void
    {
        static::creating(function (self $warehouse): void {
            $warehouse->external_id ??= (string) Str::uuid();
        });

        static::updating(function (self $warehouse): void {
            if ($warehouse->isDirty('is_active') && ! $warehouse->is_active
                && $warehouse->posTerminals()->where('is_active', true)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'is_active' => __('No puedes desactivar un almacén asignado a terminales POS activos.'),
                ]);
            }
        });

        static::created(function (self $warehouse): void {
            $warehouse->locations()->firstOrCreate(
                ['code' => 'UNASSIGNED'],
                [
                    'parent_id' => null,
                    'name' => 'Sin ubicación asignada',
                    'type' => WarehouseLocation::TYPE_UNASSIGNED,
                    'sort_order' => 0,
                    'is_active' => true,
                ],
            );
        });

        static::updated(function (self $warehouse): void {
            if (! $warehouse->wasChanged([
                'name',
                'code',
                'is_active',
                'online_fulfillment_enabled',
                'online_fulfillment_priority',
            ])) {
                return;
            }

            $productIds = Inventory::query()
                ->where('warehouse_id', $warehouse->id)
                ->whereNotNull('product_id')
                ->distinct()
                ->pluck('product_id');

            if ($productIds->isNotEmpty()) {
                Product::query()->whereIn('id', $productIds)->update([
                    'external_revision' => DB::raw('external_revision + 1'),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    protected $fillable = [
        'code', 'name', 'type', 'address', 'notes', 'is_primary', 'is_active',
        'online_fulfillment_enabled', 'online_fulfillment_priority',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'online_fulfillment_enabled' => 'boolean',
            'online_fulfillment_priority' => 'integer',
        ];
    }

    public function userAccesses(): HasMany { return $this->hasMany(WarehouseUserAccess::class); }
    public function posTerminals(): HasMany { return $this->hasMany(PosTerminal::class); }
    public function locations(): HasMany { return $this->hasMany(WarehouseLocation::class); }
    public function inventories(): HasMany { return $this->hasMany(Inventory::class); }
    public function outgoingTransfers(): HasMany { return $this->hasMany(WarehouseTransfer::class, 'source_warehouse_id'); }
    public function incomingTransfers(): HasMany { return $this->hasMany(WarehouseTransfer::class, 'destination_warehouse_id'); }
    public function transferRulesAsSource(): HasMany { return $this->hasMany(WarehouseTransferRule::class, 'source_warehouse_id'); }
    public function transferRulesAsDestination(): HasMany { return $this->hasMany(WarehouseTransferRule::class, 'destination_warehouse_id'); }

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
