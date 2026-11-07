<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryLocationBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferApproval;
use App\Models\WarehouseTransferEvent;
use App\Models\WarehouseTransferItem;
use App\Models\WarehouseTransferRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseTransferService
{
    public function create(
        Warehouse $source,
        Warehouse $destination,
        array $items,
        ?int $userId,
        ?string $notes = null,
        string $transferType = 'standard',
    ): WarehouseTransfer {
        if ($source->is($destination) || ! $source->is_active || ! $destination->is_active) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => __('Selecciona dos almacenes diferentes y activos.'),
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('Agrega al menos un producto a la transferencia.')]);
        }

        return DB::transaction(function () use ($source, $destination, $items, $userId, $notes, $transferType): WarehouseTransfer {
            $this->assertWarehousePermission($userId, $source->id, $destination->id, 'transfers.request');
            $normalized = [];
            $totalQuantity = 0;

            foreach ($items as $item) {
                $inventory = Inventory::query()->whereKey($item['inventory_id'])->firstOrFail();
                $sourceLocation = WarehouseLocation::query()->whereKey($item['source_location_id'])->firstOrFail();
                $destinationLocation = WarehouseLocation::query()->whereKey($item['destination_location_id'])->firstOrFail();
                $quantity = (int) ($item['quantity'] ?? 0);

                if ((int) $inventory->warehouse_id !== (int) $source->id
                    || (int) $sourceLocation->warehouse_id !== (int) $source->id
                    || (int) $destinationLocation->warehouse_id !== (int) $destination->id
                    || ! $sourceLocation->is_active
                    || ! $destinationLocation->is_active
                    || $destinationLocation->type === WarehouseLocation::TYPE_UNASSIGNED
                    || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => __('Cada línea debe usar inventario y ubicación de origen/destino válidos, activos y con cantidad positiva.'),
                    ]);
                }

                $normalized[] = compact('inventory', 'sourceLocation', 'destinationLocation', 'quantity');
                $totalQuantity += $quantity;
            }

            $requesterRoleSlugs = $userId
                ? User::query()->find($userId)?->roles()->pluck('slug')->all() ?? []
                : [];

            $candidateRules = WarehouseTransferRule::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('source_warehouse_id')->orWhere('source_warehouse_id', $source->id))
                ->where(fn ($query) => $query->whereNull('destination_warehouse_id')->orWhere('destination_warehouse_id', $destination->id))
                ->where(fn ($query) => $query->whereNull('transfer_type')->orWhere('transfer_type', $transferType))
                ->where(fn ($query) => $query->whereNull('min_quantity')->orWhere('min_quantity', '<=', $totalQuantity))
                ->where(fn ($query) => $query->whereNull('max_quantity')->orWhere('max_quantity', '>=', $totalQuantity))
                ->orderBy('priority')
                ->orderBy('id')
                ->get();

            $rule = $candidateRules->first(fn (WarehouseTransferRule $candidate): bool =>
                empty($candidate->requester_role_slugs)
                || count(array_intersect($candidate->requester_role_slugs, $requesterRoleSlugs)) > 0
            );

            if (! $rule && $candidateRules->isNotEmpty()) {
                throw ValidationException::withMessages(['transfer' => __('Los roles del solicitante no están autorizados por las reglas de transferencia aplicables.')]);
            }

            $requiredApprovals = $rule?->auto_approve ? 0 : max(1, (int) ($rule?->required_approvals ?? 1));
            $status = $requiredApprovals === 0
                ? WarehouseTransfer::STATUS_APPROVED
                : WarehouseTransfer::STATUS_PENDING_APPROVAL;

            $transfer = WarehouseTransfer::query()->create([
                'code' => 'TRF-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'source_warehouse_id' => $source->id,
                'destination_warehouse_id' => $destination->id,
                'requested_by' => $userId,
                'transfer_type' => $transferType,
                'status' => $status,
                'required_approvals' => $requiredApprovals,
                'requested_at' => now(),
                'approved_at' => $requiredApprovals === 0 ? now() : null,
                'notes' => $notes,
                'metadata' => [
                    'approval_rule_id' => $rule?->id,
                    'requester_can_approve' => (bool) ($rule?->requester_can_approve ?? false),
                    'approver_role_slugs' => $rule?->approver_role_slugs ?? [],
                ],
            ]);

            foreach ($normalized as $line) {
                $transfer->items()->create([
                    'source_inventory_id' => $line['inventory']->id,
                    'source_location_id' => $line['sourceLocation']->id,
                    'destination_location_id' => $line['destinationLocation']->id,
                    'quantity_requested' => $line['quantity'],
                ]);
            }

            $this->event($transfer, $userId, 'created', null, $status, [
                'total_quantity_requested' => $totalQuantity,
                'approval_rule_id' => $rule?->id,
                'required_approvals' => $requiredApprovals,
            ]);

            return $transfer->load('items');
        });
    }

    public function approve(WarehouseTransfer $transfer, int $userId, ?string $comments = null): WarehouseTransfer
    {
        return DB::transaction(function () use ($transfer, $userId, $comments): WarehouseTransfer {
            $transfer = WarehouseTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            $this->assertWarehousePermission($userId, $transfer->source_warehouse_id, $transfer->destination_warehouse_id, 'transfers.approve');

            if ($transfer->status !== WarehouseTransfer::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['transfer' => __('La transferencia ya no está pendiente de aprobación.')]);
            }

            $allowedApproverRoles = $transfer->metadata['approver_role_slugs'] ?? [];
            if ($allowedApproverRoles !== [] && ! User::query()->findOrFail($userId)->roles()->whereIn('slug', $allowedApproverRoles)->exists()) {
                throw ValidationException::withMessages(['transfer' => __('Tu rol no está autorizado para aprobar esta transferencia.')]);
            }

            if ($transfer->approvals()->where('user_id', $userId)->exists()) {
                throw ValidationException::withMessages(['transfer' => __('Este usuario ya registró una decisión para la transferencia.')]);
            }

            if ($transfer->requested_by === $userId && ! ($transfer->metadata['requester_can_approve'] ?? false)) {
                throw ValidationException::withMessages(['transfer' => __('El solicitante no puede aprobar su propia transferencia según la regla aplicada.')]);
            }

            WarehouseTransferApproval::query()->create([
                'warehouse_transfer_id' => $transfer->id,
                'user_id' => $userId,
                'decision' => 'approved',
                'comments' => $comments,
                'decided_at' => now(),
            ]);

            $approvedCount = $transfer->approvals()->where('decision', 'approved')->count();
            $from = $transfer->status;

            if ($approvedCount >= $transfer->required_approvals) {
                $transfer->update([
                    'status' => WarehouseTransfer::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);
            }

            $this->event($transfer, $userId, 'approval_recorded', $from, $transfer->status, [
                'approved_count' => $approvedCount,
                'required_approvals' => $transfer->required_approvals,
                'comments' => $comments,
            ]);

            return $transfer->refresh();
        });
    }

    public function reject(WarehouseTransfer $transfer, int $userId, string $reason): WarehouseTransfer
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('Indica el motivo del rechazo.')]);
        }

        return DB::transaction(function () use ($transfer, $userId, $reason): WarehouseTransfer {
            $transfer = WarehouseTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== WarehouseTransfer::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages(['transfer' => __('Solo se pueden rechazar transferencias pendientes de aprobación.')]);
            }

            $allowedApproverRoles = $transfer->metadata['approver_role_slugs'] ?? [];
            if ($allowedApproverRoles !== [] && ! User::query()->findOrFail($userId)->roles()->whereIn('slug', $allowedApproverRoles)->exists()) {
                throw ValidationException::withMessages(['transfer' => __('Tu rol no está autorizado para rechazar esta transferencia.')]);
            }

            if ($transfer->requested_by === $userId && ! ($transfer->metadata['requester_can_approve'] ?? false)) {
                throw ValidationException::withMessages(['transfer' => __('El solicitante no puede decidir sobre su propia transferencia.')]);
            }

            if ($transfer->approvals()->where('user_id', $userId)->exists()) {
                throw ValidationException::withMessages(['transfer' => __('Este usuario ya registró una decisión para la transferencia.')]);
            }

            WarehouseTransferApproval::query()->create([
                'warehouse_transfer_id' => $transfer->id,
                'user_id' => $userId,
                'decision' => 'rejected',
                'comments' => $reason,
                'decided_at' => now(),
            ]);
            $from = $transfer->status;
            $transfer->update([
                'status' => WarehouseTransfer::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->event($transfer, $userId, 'rejected', $from, $transfer->status, ['reason' => $reason]);

            return $transfer->refresh();
        });
    }

    /**
     * Dispatch a single shipment. $quantities maps transfer item IDs to quantities.
     * Stock leaves the source warehouse only here; creation and approval do not move stock.
     */
    public function dispatch(WarehouseTransfer $transfer, array $quantities, int $userId): WarehouseTransfer
    {
        return DB::transaction(function () use ($transfer, $quantities, $userId): WarehouseTransfer {
            $transfer = WarehouseTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== WarehouseTransfer::STATUS_APPROVED) {
                throw ValidationException::withMessages(['transfer' => __('La transferencia debe estar aprobada antes del despacho.')]);
            }

            $dispatchedTotal = 0;
            foreach ($transfer->items()->lockForUpdate()->get() as $item) {
                $quantity = (int) ($quantities[$item->id] ?? 0);
                if ($quantity < 0 || $quantity > $item->quantity_requested) {
                    throw ValidationException::withMessages(['quantities' => __('La cantidad despachada no puede superar la solicitada.')]);
                }
                if ($quantity === 0) {
                    continue;
                }

                $inventory = Inventory::query()->whereKey($item->source_inventory_id)->lockForUpdate()->firstOrFail();
                $location = WarehouseLocation::query()->whereKey($item->source_location_id)->firstOrFail();
                if ((int) $location->warehouse_id !== (int) $transfer->source_warehouse_id || ! $location->is_active) {
                    throw ValidationException::withMessages(['quantities' => __('La ubicación de origen ya no está activa o no pertenece al almacén de origen.')]);
                }
                $balance = $this->balanceFor($inventory, $location);
                $available = $inventory->quantity_on_hand - $inventory->quantity_reserved - $inventory->quantity_blocked;

                if ($balance->quantity_on_hand < $quantity || $available < $quantity) {
                    throw ValidationException::withMessages([
                        'quantities' => __('No hay existencia disponible suficiente en el almacén o ubicación de origen.'),
                    ]);
                }

                $balance->decrement('quantity_on_hand', $quantity);
                $inventory->decrement('quantity_on_hand', $quantity);
                $item->update(['quantity_dispatched' => $quantity]);

                InventoryMovement::query()->create([
                    'inventory_id' => $inventory->id,
                    'user_id' => $userId,
                    'from_location_id' => $location->id,
                    'type' => 'transfer_dispatch',
                    'quantity_delta' => -$quantity,
                    'reason' => 'Despacho '.$transfer->code,
                    'reference_type' => WarehouseTransfer::class,
                    'reference_id' => $transfer->id,
                    'metadata' => ['transfer_item_id' => $item->id, 'transfer_code' => $transfer->code],
                ]);

                $dispatchedTotal += $quantity;
            }

            if ($dispatchedTotal === 0) {
                throw ValidationException::withMessages(['quantities' => __('Indica al menos una cantidad positiva para despachar.')]);
            }

            $from = $transfer->status;
            $transfer->update([
                'status' => WarehouseTransfer::STATUS_IN_TRANSIT,
                'dispatched_at' => now(),
            ]);

            $this->event($transfer, $userId, 'dispatched', $from, $transfer->status, [
                'total_quantity_dispatched' => $dispatchedTotal,
                'lines' => $transfer->items()->get(['id', 'quantity_dispatched'])->toArray(),
            ]);

            return $transfer->refresh()->load('items');
        });
    }

    /**
     * Receive quantities by item ID. Damaged units are accounted for but never added to stock.
     */
    public function receive(WarehouseTransfer $transfer, array $quantities, int $userId): WarehouseTransfer
    {
        return DB::transaction(function () use ($transfer, $quantities, $userId): WarehouseTransfer {
            $transfer = WarehouseTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($transfer->status, [
                WarehouseTransfer::STATUS_IN_TRANSIT,
                WarehouseTransfer::STATUS_PARTIALLY_RECEIVED,
            ], true)) {
                throw ValidationException::withMessages(['transfer' => __('La transferencia no está en tránsito ni admite recepción.')]);
            }

            $receivedNow = 0;
            foreach ($transfer->items()->lockForUpdate()->get() as $item) {
                $input = $quantities[$item->id] ?? [];
                $received = (int) ($input['received'] ?? 0);
                $damaged = (int) ($input['damaged'] ?? 0);
                $remaining = $item->quantity_dispatched - $item->quantity_received - $item->quantity_damaged;

                if ($received < 0 || $damaged < 0 || ($received + $damaged) > $remaining) {
                    throw ValidationException::withMessages(['quantities' => __('La recepción no puede superar las unidades pendientes de esta línea.')]);
                }

                if ($received === 0 && $damaged === 0) {
                    continue;
                }

                if ($received > 0) {
                    $sourceInventory = Inventory::query()->whereKey($item->source_inventory_id)->firstOrFail();
                    $destinationInventory = Inventory::query()->firstOrCreate(
                        [
                            'warehouse_id' => $transfer->destination_warehouse_id,
                            'stock_key' => $sourceInventory->stock_key,
                        ],
                        [
                            'product_id' => $sourceInventory->product_id,
                            'product_variant_id' => $sourceInventory->product_variant_id,
                            'quantity_on_hand' => 0,
                            'quantity_reserved' => 0,
                            'quantity_blocked' => 0,
                        ],
                    );
                    $destinationInventory = Inventory::query()->whereKey($destinationInventory->id)->lockForUpdate()->firstOrFail();
                    $destinationLocation = WarehouseLocation::query()->whereKey($item->destination_location_id)->firstOrFail();
                    if ((int) $destinationLocation->warehouse_id !== (int) $transfer->destination_warehouse_id || ! $destinationLocation->is_active || $destinationLocation->type === WarehouseLocation::TYPE_UNASSIGNED) {
                        throw ValidationException::withMessages(['quantities' => __('La ubicación de destino ya no está activa o no pertenece al almacén de destino.')]);
                    }
                    $destinationBalance = $this->balanceFor($destinationInventory, $destinationLocation);

                    $destinationBalance->increment('quantity_on_hand', $received);
                    $destinationInventory->increment('quantity_on_hand', $received);
                    $item->destination_inventory_id = $destinationInventory->id;
                }

                $item->quantity_received += $received;
                $item->quantity_damaged += $damaged;
                $item->save();
                $receivedNow += $received + $damaged;

                if ($received > 0) {
                    InventoryMovement::query()->create([
                        'inventory_id' => $item->destination_inventory_id,
                        'user_id' => $userId,
                        'to_location_id' => $item->destination_location_id,
                        'type' => 'transfer_receipt',
                        'quantity_delta' => $received,
                        'reason' => 'Recepción '.$transfer->code,
                        'reference_type' => WarehouseTransfer::class,
                        'reference_id' => $transfer->id,
                        'metadata' => [
                            'transfer_item_id' => $item->id,
                            'transfer_code' => $transfer->code,
                            'damaged_quantity' => $damaged,
                        ],
                    ]);
                }

                if ($damaged > 0) {
                    $this->event($transfer, $userId, 'damage_reported', $transfer->status, $transfer->status, [
                        'transfer_item_id' => $item->id,
                        'damaged_quantity' => $damaged,
                    ]);
                }
            }

            if ($receivedNow === 0) {
                throw ValidationException::withMessages(['quantities' => __('Indica unidades recibidas o dañadas para registrar la recepción.')]);
            }

            $allAccountedFor = $transfer->items()->get()->every(
                fn (WarehouseTransferItem $item): bool =>
                    ($item->quantity_received + $item->quantity_damaged) === $item->quantity_dispatched
            );
            $from = $transfer->status;
            $to = $allAccountedFor
                ? WarehouseTransfer::STATUS_RECEIVED
                : WarehouseTransfer::STATUS_PARTIALLY_RECEIVED;

            $transfer->update([
                'status' => $to,
                'completed_at' => $allAccountedFor ? now() : null,
            ]);

            $this->event($transfer, $userId, 'receipt_recorded', $from, $to, [
                'quantity_accounted_for' => $receivedNow,
                'all_dispatched_units_accounted_for' => $allAccountedFor,
            ]);

            return $transfer->refresh()->load('items');
        });
    }

    private function balanceFor(Inventory $inventory, WarehouseLocation $location): InventoryLocationBalance
    {
        return InventoryLocationBalance::query()->firstOrCreate(
            ['inventory_id' => $inventory->id, 'warehouse_location_id' => $location->id],
            ['quantity_on_hand' => 0],
        );
    }

    private function assertWarehousePermission(?int $userId, int $sourceWarehouseId, int $destinationWarehouseId, string $permission): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->findOrFail($userId);

        foreach ([$sourceWarehouseId, $destinationWarehouseId] as $warehouseId) {
            if (! $user->hasWarehousePermission($warehouseId, $permission)) {
                throw new \\Illuminate\\Auth\\Access\\AuthorizationException('No tienes permiso para realizar esta operación en ambos almacenes.');
            }
        }
    }

    private function event(
        WarehouseTransfer $transfer,
        ?int $userId,
        string $event,
        ?string $from,
        ?string $to,
        array $details = [],
    ): void {
        WarehouseTransferEvent::query()->create([
            'warehouse_transfer_id' => $transfer->id,
            'user_id' => $userId,
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'details' => $details,
        ]);
    }
}
