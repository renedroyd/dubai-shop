<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'channel', 'pos_terminal_id', 'pos_warehouse_id', 'pos_cash_session_id', 'cashier_id', 'public_token_hash', 'user_id', 'customer_name', 'customer_email', 'customer_phone',
        'shipping_address', 'shipping_address_line_2', 'shipping_city', 'shipping_locality', 'shipping_region',
        'shipping_postal_code', 'shipping_country', 'shipping_zone_id', 'shipping_zone_label', 'customer_note', 'currency',
        'subtotal_amount', 'shipping_amount', 'tax_amount', 'total_amount', 'delivery_commitment_hours', 'delivery_due_at',
        'status', 'payment_status', 'placed_at', 'confirmed_at', 'cancelled_at', 'inventory_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer', 'shipping_amount' => 'integer',
            'tax_amount' => 'integer', 'total_amount' => 'integer',
            'delivery_commitment_hours' => 'integer', 'delivery_due_at' => 'datetime',
            'placed_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'inventory_processed_at' => 'datetime',
        ];
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        $warehouseIds = $user->warehouseIdsWithPermission('view');

        if ($warehouseIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $orders) use ($warehouseIds): void {
            $orders->where(fn (Builder $pos) => $pos->where('channel', 'pos')->whereIn('pos_warehouse_id', $warehouseIds))
                ->orWhere(fn (Builder $web) => $web->where('channel', '!=', 'pos')
                    ->whereDoesntHave('items.inventory', fn (Builder $inventory) => $inventory->whereNotIn('warehouse_id', $warehouseIds)));
        });
    }

    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function shippingZone(): BelongsTo { return $this->belongsTo(ShippingZone::class); }
    public function shipment(): HasOne { return $this->hasOne(Shipment::class); }
    public function posTerminal(): BelongsTo { return $this->belongsTo(PosTerminal::class); }
    public function posWarehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'pos_warehouse_id'); }
    public function posCashSession(): BelongsTo { return $this->belongsTo(PosCashSession::class, 'pos_cash_session_id'); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function posRefunds(): HasMany { return $this->hasMany(PosRefund::class); }
}
