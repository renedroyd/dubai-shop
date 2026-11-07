<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PosTerminal extends Model
{
    protected $fillable = [
        'pos_location_id', 'warehouse_id', 'code', 'name', 'notes', 'receipt_width', 'receipt_copies', 'printer_name', 'cash_drawer_enabled', 'cash_drawer_pin', 'assigned_cashier_ids', 'cash_limit_amount', 'supervised_closing', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'receipt_copies' => 'integer', 'cash_drawer_enabled' => 'boolean', 'cash_drawer_pin' => 'integer', 'assigned_cashier_ids' => 'array', 'cash_limit_amount' => 'integer', 'supervised_closing' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $terminal): void {
            $duplicateCode = self::query()
                ->where('pos_location_id', $terminal->pos_location_id)
                ->where('code', $terminal->code)
                ->when($terminal->exists, fn ($query) => $query->whereKeyNot($terminal->getKey()))
                ->exists();

            if ($duplicateCode) {
                throw ValidationException::withMessages([
                    'code' => __('Ya existe un terminal con ese código en este establecimiento.'),
                ]);
            }

            if ($terminal->exists
                && ($terminal->isDirty('warehouse_id') || ($terminal->isDirty('is_active') && ! $terminal->is_active))
                && $terminal->cashSessions()->where('status', PosCashSession::STATUS_OPEN)->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => __('No puedes desactivar ni reasignar un terminal mientras tenga una caja abierta.'),
                ]);
            }

            if (! $terminal->is_active) {
                return;
            }

            $location = PosLocation::query()->find($terminal->pos_location_id);
            $warehouse = Warehouse::query()->find($terminal->warehouse_id);

            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages([
                    'pos_location_id' => __('El establecimiento debe existir y estar activo.'),
                ]);
            }

            if (! $warehouse || ! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => __('El almacén asignado debe existir y estar activo.'),
                ]);
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(PosLocation::class, 'pos_location_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(PosCashSession::class, 'pos_terminal_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_terminal_id');
    }
}
