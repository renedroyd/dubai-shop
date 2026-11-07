<?php

namespace App\Services\Pos;

use App\Models\PosCashMovement;
use App\Models\PosCashSession;
use App\Models\PosTerminal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosCashSessionService
{
    public function open(
        PosTerminal $terminal,
        int $userId,
        string $currency,
        int $openingAmount,
        ?string $note = null,
    ): PosCashSession {
        if ($openingAmount < 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['opening_cash_amount' => 'El importe inicial o la moneda no son válidos.']);
        }

        return DB::transaction(function () use ($terminal, $userId, $currency, $openingAmount, $note): PosCashSession {
            $lockedTerminal = PosTerminal::query()->lockForUpdate()->with(['location', 'warehouse'])->findOrFail($terminal->id);

            if (! User::query()->findOrFail($userId)->hasWarehousePermission($lockedTerminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para operar cajas en este almacén.');
            }

            if (! $lockedTerminal->is_active || ! $lockedTerminal->location?->is_active || ! $lockedTerminal->warehouse?->is_active) {
                throw ValidationException::withMessages(['pos_terminal_id' => 'El terminal, establecimiento y almacén deben estar activos.']);
            }

            $alreadyOpen = PosCashSession::query()
                ->where('pos_terminal_id', $lockedTerminal->id)
                ->where('status', PosCashSession::STATUS_OPEN)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages(['pos_terminal_id' => 'Este terminal ya tiene una sesión de caja abierta.']);
            }

            return PosCashSession::query()->create([
                'pos_terminal_id' => $lockedTerminal->id,
                'opened_by' => $userId,
                'status' => PosCashSession::STATUS_OPEN,
                'currency' => $currency,
                'opening_cash_amount' => $openingAmount,
                'opening_note' => $note,
                'opened_at' => now(),
            ]);
        });
    }

    public function recordCashMovement(
        PosCashSession $session,
        int $userId,
        string $type,
        int $amount,
        string $note,
        ?string $reference = null,
    ): PosCashMovement {
        if (! in_array($type, [PosCashMovement::TYPE_CASH_IN, PosCashMovement::TYPE_CASH_OUT], true) || $amount <= 0 || trim($note) === '') {
            throw ValidationException::withMessages(['amount' => 'Indica un importe positivo y un motivo válido para la entrada o salida de efectivo.']);
        }

        return DB::transaction(function () use ($session, $userId, $type, $amount, $note, $reference): PosCashMovement {
            $locked = PosCashSession::query()->with('terminal')->lockForUpdate()->findOrFail($session->id);

            if (! User::query()->findOrFail($userId)->hasWarehousePermission($locked->terminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para operar cajas en este almacén.');
            }

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['session' => 'No se pueden registrar movimientos en una caja cerrada.']);
            }

            if ($type === PosCashMovement::TYPE_CASH_OUT) {
                $totals = $locked->movements()
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_in", [
                        PosCashMovement::TYPE_CASH_IN, PosCashMovement::TYPE_SALE,
                    ])
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_out", [
                        PosCashMovement::TYPE_CASH_OUT, PosCashMovement::TYPE_REFUND,
                    ])
                    ->first();
                $availableCash = (int) $locked->opening_cash_amount + (int) $totals->cash_in - (int) $totals->cash_out;

                if ($amount > $availableCash) {
                    throw ValidationException::withMessages(['amount' => 'La salida supera el efectivo disponible en la caja.']);
                }
            }

            return $locked->movements()->create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'currency' => $locked->currency,
                'reference' => $reference,
                'note' => trim($note),
            ]);
        });
    }

    public function close(
        PosCashSession $session,
        int $userId,
        int $countedAmount,
        ?string $note = null,
    ): PosCashSession {
        if ($countedAmount < 0) {
            throw ValidationException::withMessages(['counted_cash_amount' => 'El efectivo contado no puede ser negativo.']);
        }

        return DB::transaction(function () use ($session, $userId, $countedAmount, $note): PosCashSession {
            $locked = PosCashSession::query()->with('terminal')->lockForUpdate()->findOrFail($session->id);

            if (! User::query()->findOrFail($userId)->hasWarehousePermission($locked->terminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para operar cajas en este almacén.');
            }

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['session' => 'La sesión ya está cerrada.']);
            }

            $totals = $locked->movements()
                ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_in", [
                    PosCashMovement::TYPE_CASH_IN, PosCashMovement::TYPE_SALE,
                ])
                ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_out", [
                    PosCashMovement::TYPE_CASH_OUT, PosCashMovement::TYPE_REFUND,
                ])
                ->first();

            $expected = (int) $locked->opening_cash_amount + (int) $totals->cash_in - (int) $totals->cash_out;

            if ($expected < 0) {
                throw ValidationException::withMessages(['session' => 'Los movimientos registrados producen un saldo de caja negativo. Revisa los movimientos antes de cerrar.']);
            }

            $locked->update([
                'status' => PosCashSession::STATUS_CLOSED,
                'closed_by' => $userId,
                'expected_cash_amount' => $expected,
                'counted_cash_amount' => $countedAmount,
                'difference_amount' => $countedAmount - $expected,
                'closing_note' => $note,
                'closed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}