<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_REVIEW = 'awaiting_review';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PENDING_COLLECTION = 'pending_collection';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id', 'payment_method_id', 'driver', 'status', 'amount', 'currency',
        'reference', 'proof_path', 'customer_note', 'reviewed_by', 'reviewed_at',
        'review_note', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'reviewed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function method(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function events(): HasMany { return $this->hasMany(PaymentEvent::class)->latest(); }
    public function posCashMovements(): HasMany { return $this->hasMany(PosCashMovement::class); }
    public function posRefunds(): HasMany { return $this->hasMany(PosRefund::class); }
}
