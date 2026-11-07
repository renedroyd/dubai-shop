<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRefundPayment extends Model
{
    protected $fillable = ['pos_refund_id', 'payment_id', 'driver', 'amount', 'currency', 'reference'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function refund(): BelongsTo { return $this->belongsTo(PosRefund::class, 'pos_refund_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
