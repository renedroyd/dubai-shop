<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'number', 'public_token_hash', 'user_id', 'customer_name', 'customer_email', 'customer_phone',
        'shipping_address', 'shipping_address_line_2', 'shipping_city', 'shipping_region',
        'shipping_postal_code', 'shipping_country', 'customer_note', 'currency',
        'subtotal_amount', 'shipping_amount', 'tax_amount', 'total_amount',
        'status', 'payment_status', 'placed_at', 'confirmed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'integer', 'shipping_amount' => 'integer',
            'tax_amount' => 'integer', 'total_amount' => 'integer',
            'placed_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
