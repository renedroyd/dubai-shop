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

    public function expiringPointsWithin(int $days = 30): int
    {
        $credits = $this->entries()
            ->where('points_delta', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->orderBy('expires_at')
            ->get();

        return (int) $credits->sum(function (PosLoyaltyEntry $credit): int {
            $consumed = (int) $this->entries()
                ->where('metadata->source_entry_id', $credit->id)
                ->sum('points_delta');

            return max(0, (int) $credit->points_delta + $consumed);
        });
    }

}
