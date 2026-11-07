<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosLoyaltyWallet extends Model
{
    protected $fillable = ['user_id', 'currency', 'points_balance'];

    protected function casts(): array
    {
        return ['points_balance' => 'integer'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function entries(): HasMany { return $this->hasMany(PosLoyaltyEntry::class, 'wallet_id'); }

    public function availablePoints(): int
    {
        return (int) $this->entries()->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->sum('points_delta');
    }
}
