<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosRefund extends Model
{
    protected $fillable = [
        'order_id', 'payment_id', 'pos_cash_session_id', 'processed_by',
        'amount', 'currency', 'status', 'reason', 'reference', 'refunded_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function session(): BelongsTo { return $this->belongsTo(PosCashSession::class, 'pos_cash_session_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
    public function items(): HasMany { return $this->hasMany(PosRefundItem::class); }
}