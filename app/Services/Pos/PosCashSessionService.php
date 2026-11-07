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
        ?string $shiftLabel = null,
    ): PosCashSession {
        if ($openingAmount < 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages(['opening_cash_amount' => 'El importe inicial o la moneda no son válidos.']);
        }

        return DB::transaction(function () use ($terminal, $userId, $currency, $openingAmount, $note, $shiftLabel): PosCashSession {
            $lockedTerminal = PosTerminal::query()->lockForUpdate()->with(['location', 'warehouse'])->findOrFail($terminal->id);
            $cashier = User::query()->findOrFail($userId);

            if (! $cashier->hasWarehousePermission($lockedTerminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para operar cajas en este almacén.');
            }
            $this->assertAssignedCashier($cashier, $lockedTerminal);

            if (! $lockedTerminal->is_active || ! $lockedTerminal->location?->is_active || ! $lockedTerminal->warehouse?->is_active) {
                throw ValidationException::withMessages(['pos_terminal_id' => 'El terminal, establecimiento y almacén deben estar activos.']);
            }

            if ($lockedTerminal->cash_limit_amount !== null && $openingAmount > $lockedTerminal->cash_limit_amount) {
                throw ValidationException::withMessages(['opening_cash_amount' => 'El fondo inicial supera el límite de efectivo configurado para este terminal.']);
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
                'shift_label' => filled($shiftLabel) ? trim($shiftLabel) : null,
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
            $cashier = User::query()->findOrFail($userId);

            if (! $cashier->hasWarehousePermission($locked->terminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para operar cajas en este almacén.');
            }
            $this->assertAssignedCashier($cashier, $locked->terminal);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['session' => 'La caja está cerrada o pendiente de aprobación de cierre.']);
            }

            $totals = $this->cashTotals($locked);
            $availableCash = (int) $locked->opening_cash_amount + (int) $totals->cash_in - (int) $totals->cash_out;

            if ($type === PosCashMovement::TYPE_CASH_OUT && $amount > $availableCash) {
                throw ValidationException::withMessages(['amount' => 'La salida supera el efectivo disponible en la caja.']);
            }

            if ($type === PosCashMovement::TYPE_CASH_IN
                && $locked->terminal->cash_limit_amount !== null
                && $availableCash + $amount > $locked->terminal->cash_limit_amount) {
                throw ValidationException::withMessages(['amount' => 'La entrada supera el límite de efectivo configurado para este terminal.']);
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
            $cashier = User::query()->findOrFail($userId);

            if (! $cashier->hasWarehousePermission($locked->terminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para operar cajas en este almacén.');
            }
            $this->assertAssignedCashier($cashier, $locked->terminal);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['session' => 'La sesión ya está cerrada o tiene un cierre pendiente de aprobación.']);
            }

            $expected = $this->expectedCash($locked);

            if ($locked->terminal->supervised_closing) {
                $locked->update([
                    'close_requested_by' => $userId,
                    'close_requested_at' => now(),
                    'close_counted_cash_amount' => $countedAmount,
                    'close_request_note' => $note,
                    'expected_cash_amount' => $expected,
                    'counted_cash_amount' => $countedAmount,
                    'difference_amount' => $countedAmount - $expected,
                ]);

                return $locked->refresh();
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

    public function approveClose(PosCashSession $session, int $approverId): PosCashSession
    {
        return DB::transaction(function () use ($session, $approverId): PosCashSession {
            $locked = PosCashSession::query()->with('terminal')->lockForUpdate()->findOrFail($session->id);
            $approver = User::query()->findOrFail($approverId);

            if (! $approver->hasAdminPermission('pos_cash_sessions.approve_close')
                || ! $approver->hasWarehousePermission($locked->terminal->warehouse_id, 'pos.operate')) {
                throw new AuthorizationException('No tienes permiso para aprobar cierres de caja en este almacén.');
            }

            if ($locked->closed_at !== null || $locked->close_requested_at === null || ! $locked->isOpen() && $locked->status !== PosCashSession::STATUS_OPEN) {
                throw ValidationException::withMessages(['session' => 'No existe una solicitud de cierre pendiente para aprobar.']);
            }

            if ((int) $locked->close_requested_by === $approverId) {
                throw ValidationException::withMessages(['session' => 'El cajero que solicita el cierre no puede aprobar su propio cierre.']);
            }

            $expected = $this->expectedCash($locked);
            $counted = (int) $locked->close_counted_cash_amount;

            $locked->update([
                'status' => PosCashSession::STATUS_CLOSED,
                'closed_by' => $locked->close_requested_by,
                'close_approved_by' => $approverId,
                'close_approved_at' => now(),
                'expected_cash_amount' => $expected,
                'counted_cash_amount' => $counted,
                'difference_amount' => $counted - $expected,
                'closing_note' => $locked->close_request_note,
                'closed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    private function assertAssignedCashier(User $user, PosTerminal $terminal): void
    {
        $assignedIds = array_map('intval', $terminal->assigned_cashier_ids ?? []);

        if ($assignedIds !== [] && ! in_array((int) $user->id, $assignedIds, true)) {
            throw new AuthorizationException('No estás asignado a este terminal POS.');
        }
    }

    private function cashTotals(PosCashSession $session): object
    {
        return $session->movements()
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_in", [
                PosCashMovement::TYPE_CASH_IN, PosCashMovement::TYPE_SALE,
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE 0 END), 0) as cash_out", [
                PosCashMovement::TYPE_CASH_OUT, PosCashMovement::TYPE_REFUND,
            ])->first();
    }

    private function expectedCash(PosCashSession $session): int
    {
        $totals = $this->cashTotals($session);
        $expected = (int) $session->opening_cash_amount + (int) $totals->cash_in - (int) $totals->cash_out;

        if ($expected < 0) {
            throw ValidationException::withMessages(['session' => 'Los movimientos registrados producen un saldo de caja negativo. Revisa los movimientos antes de cerrar.']);
        }

        return $expected;
    }
}
