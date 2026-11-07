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

    public function previewRedemption(?int $userId, string $currency, int $eligibleAmount, int $requestedPoints): array
    {
        if ($requestedPoints <= 0) {
            return ['points' => 0, 'discount_amount' => 0, 'available_points' => 0, 'error' => null];
        }

        $setting = PosLoyaltySetting::query()->where('currency', $currency)->where('is_enabled', true)->first();
        $wallet = $userId
            ? PosLoyaltyWallet::query()->where('user_id', $userId)->where('currency', $currency)->first()
            : null;

        if (! $setting || ! $wallet) {
            return ['points' => 0, 'discount_amount' => 0, 'available_points' => 0, 'error' => 'Este cliente no tiene saldo de puntos disponible en la moneda de esta venta.'];
        }

        $available = $wallet->availablePoints();
        $discount = $requestedPoints * (int) $setting->redemption_amount;
        $maximumDiscount = intdiv($eligibleAmount * (int) $setting->maximum_redemption_basis_points, 10000);

        $error = null;
        if ($requestedPoints < (int) $setting->minimum_redemption_points) {
            $error = 'El canje mínimo es de '.(int) $setting->minimum_redemption_points.' puntos.';
        } elseif ($requestedPoints > $available) {
            $error = 'El cliente no dispone de suficientes puntos en esta moneda.';
        } elseif ($discount > $maximumDiscount) {
            $error = 'El canje supera el límite del 20 % del subtotal elegible.';
        }

        return [
            'points' => $error ? 0 : $requestedPoints,
            'discount_amount' => $error ? 0 : $discount,
            'available_points' => $available,
            'maximum_discount_amount' => $maximumDiscount,
            'error' => $error,
        ];
    }

    public function prepareRedemption(int $userId, string $currency, int $eligibleAmount, int $requestedPoints): array
    {
        return DB::transaction(function () use ($userId, $currency, $eligibleAmount, $requestedPoints): array {
            $setting = PosLoyaltySetting::query()->where('currency', $currency)->where('is_enabled', true)->first();
            $wallet = PosLoyaltyWallet::query()
                ->where('user_id', $userId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (! $setting || ! $wallet) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages(['loyaltyPointsToRedeem' => 'Este cliente no tiene saldo de puntos disponible en la moneda de esta venta.']);
            }

            $available = $wallet->availablePoints();
            $discount = $requestedPoints * (int) $setting->redemption_amount;
            $maximumDiscount = intdiv($eligibleAmount * (int) $setting->maximum_redemption_basis_points, 10000);

            if ($requestedPoints < (int) $setting->minimum_redemption_points) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages(['loyaltyPointsToRedeem' => 'El canje mínimo es de '.(int) $setting->minimum_redemption_points.' puntos.']);
            }
            if ($requestedPoints > $available) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages(['loyaltyPointsToRedeem' => 'El cliente no dispone de suficientes puntos en esta moneda.']);
            }
            if ($discount <= 0 || $discount > $maximumDiscount) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages(['loyaltyPointsToRedeem' => 'El canje supera el límite del 20 % del subtotal elegible.']);
            }

            $credits = $wallet->entries()
                ->where('points_delta', '>', 0)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remainingToAllocate = $requestedPoints;
            $allocations = [];
            foreach ($credits as $credit) {
                $consumed = (int) $wallet->entries()
                    ->where('metadata->source_entry_id', $credit->id)
                    ->sum('points_delta');
                $remaining = max(0, (int) $credit->points_delta + $consumed);
                $take = min($remaining, $remainingToAllocate);
                if ($take > 0) {
                    $allocations[] = [
                        'source_entry_id' => $credit->id,
                        'points' => $take,
                        'expires_at' => $credit->expires_at,
                    ];
                    $remainingToAllocate -= $take;
                }
                if ($remainingToAllocate === 0) break;
            }

            if ($remainingToAllocate > 0) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages(['loyaltyPointsToRedeem' => 'No fue posible reservar todos los puntos seleccionados. Actualiza el saldo e inténtalo de nuevo.']);
            }

            return [
                'wallet_id' => $wallet->id,
                'points' => $requestedPoints,
                'discount_amount' => $discount,
                'redemption_amount' => (int) $setting->redemption_amount,
                'allocations' => $allocations,
            ];
        });
    }

    public function recordRedemption(Order $order, array $redemption): void
    {
        DB::transaction(function () use ($order, $redemption): void {
            $wallet = PosLoyaltyWallet::query()->lockForUpdate()->findOrFail($redemption['wallet_id']);
            foreach ($redemption['allocations'] as $allocation) {
                $key = 'pos-order-redeemed:'.$order->id.':'.$allocation['source_entry_id'];
                if ($wallet->entries()->where('idempotency_key', $key)->exists()) continue;

                $wallet->entries()->create([
                    'order_id' => $order->id,
                    'type' => 'redeem',
                    'points_delta' => -1 * (int) $allocation['points'],
                    'idempotency_key' => $key,
                    'expires_at' => $allocation['expires_at'],
                    'metadata' => [
                        'source_entry_id' => (int) $allocation['source_entry_id'],
                        'redemption_amount_per_point' => (int) $redemption['redemption_amount'],
                        'discount_amount' => (int) $allocation['points'] * (int) $redemption['redemption_amount'],
                    ],
                ]);
            }

            $wallet->update(['points_balance' => max(0, $wallet->availablePoints())]);
        });
    }

    public function restoreRedemptionForCancelledOrder(Order $order, ?int $actorId = null): void
    {
        DB::transaction(function () use ($order, $actorId): void {
            $redemptions = PosLoyaltyEntry::query()
                ->where('order_id', $order->id)
                ->where('type', 'redeem')
                ->where('points_delta', '<', 0)
                ->orderBy('id')
                ->get();

            if ($redemptions->isEmpty()) return;

            $wallet = PosLoyaltyWallet::query()->lockForUpdate()->findOrFail($redemptions->first()->wallet_id);
            foreach ($redemptions as $redemption) {
                $key = 'pos-order-redeem-reversed:'.$redemption->id;
                if ($wallet->entries()->where('idempotency_key', $key)->exists()) continue;

                $wallet->entries()->create([
                    'order_id' => $order->id,
                    'actor_id' => $actorId,
                    'type' => 'redemption_reversal',
                    'points_delta' => abs((int) $redemption->points_delta),
                    'idempotency_key' => $key,
                    'expires_at' => $redemption->expires_at,
                    'metadata' => [
                        'source_entry_id' => $redemption->metadata['source_entry_id'] ?? null,
                        'reversed_entry_id' => $redemption->id,
                        'reason' => 'pos_order_cancelled',
                    ],
                ]);
            }

            $wallet->update(['points_balance' => max(0, $wallet->availablePoints())]);
        });
    }

}
