<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosLoyaltyEntry extends Model
{
    protected $fillable = ['wallet_id', 'order_id', 'actor_id', 'type', 'points_delta', 'idempotency_key', 'expires_at', 'metadata'];

    protected function casts(): array
    {
        return ['points_delta' => 'integer', 'expires_at' => 'datetime', 'metadata' => 'array'];
    }

    public function wallet(): BelongsTo { return $this->belongsTo(PosLoyaltyWallet::class, 'wallet_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
