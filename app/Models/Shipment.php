<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_DELIVERY_FAILED = 'delivery_failed';
    public const STATUS_DELIVERED = 'delivered';

    protected $fillable = ['order_id', 'tracking_code', 'status', 'courier_name', 'courier_phone', 'dispatched_at', 'delivered_at', 'recipient_name', 'delivery_note'];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function attempts(): HasMany { return $this->hasMany(DeliveryAttempt::class)->orderByDesc('attempted_at'); }
    public function events(): HasMany { return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at'); }
}
