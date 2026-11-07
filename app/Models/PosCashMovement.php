<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCashMovement extends Model
{
    public const TYPE_CASH_IN = 'cash_in';
    public const TYPE_CASH_OUT = 'cash_out';
    public const TYPE_SALE = 'sale';
    public const TYPE_REFUND = 'refund';

    protected $fillable = [
        'pos_cash_session_id', 'payment_id', 'user_id', 'type', 'amount',
        'currency', 'reference', 'note', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'metadata' => 'array'];
    }

    public function session(): BelongsTo { return $this->belongsTo(PosCashSession::class, 'pos_cash_session_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function refund(): BelongsTo { return $this->belongsTo(PosRefund::class, 'pos_refund_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}