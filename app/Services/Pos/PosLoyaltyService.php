<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PosLoyaltyEntry;
use App\Models\PosLoyaltySetting;
use App\Models\PosLoyaltyWallet;
use Illuminate\Support\Facades\DB;

class PosLoyaltyService
{
    public function awardForPaidPosOrder(Order $order): ?PosLoyaltyEntry
    {
        return DB::transaction(function () use ($order): ?PosLoyaltyEntry {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->channel !== 'pos'
                || $lockedOrder->user_id === null
                || $lockedOrder->payment_status !== 'paid'
                || (int) $lockedOrder->payments()->where('status', Payment::STATUS_PAID)->sum('amount') < (int) $lockedOrder->total_amount) {
                return null;
            }

            $setting = PosLoyaltySetting::query()
                ->where('currency', $lockedOrder->currency)
                ->where('is_enabled', true)
                ->first();

            if (! $setting || $setting->earning_amount < 1 || $setting->points_per_earning_unit < 1) {
                return null;
            }

            $idempotencyKey = 'pos-order-earned:'.$lockedOrder->id;
            if (PosLoyaltyEntry::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return null;
            }

            $points = intdiv((int) $lockedOrder->total_amount, (int) $setting->earning_amount)
                * (int) $setting->points_per_earning_unit;

            if ($points < 1) {
                return null;
            }

            $wallet = PosLoyaltyWallet::query()->firstOrCreate(
                ['user_id' => $lockedOrder->user_id, 'currency' => $lockedOrder->currency],
                ['points_balance' => 0],
            );
            $wallet = PosLoyaltyWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $entry = $wallet->entries()->create([
                'order_id' => $lockedOrder->id,
                'type' => 'earn',
                'points_delta' => $points,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMonths((int) $setting->expiration_months),
                'metadata' => [
                    'currency' => $lockedOrder->currency,
                    'eligible_amount' => (int) $lockedOrder->total_amount,
                    'earning_amount' => (int) $setting->earning_amount,
                    'points_per_earning_unit' => (int) $setting->points_per_earning_unit,
                    'rule_version' => 1,
                ],
            ]);

            $wallet->update(['points_balance' => $wallet->availablePoints()]);

            return $entry;
        });
    }
}
