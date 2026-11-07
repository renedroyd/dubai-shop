<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Models\WarehouseLocation;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferRule;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\WarehouseTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseTransferTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto transferencia',
            'slug' => 'producto-transferencia-'.uniqid(),
            'sku' => 'TRF-'.strtoupper(uniqid()),
            'price_amount' => 1500,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    private function warehouse(string $code): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Almacén '.$code,
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    private function grantTransferAccess(User $user, Warehouse ...$warehouses): void
    {
        $user->update(['admin_access_enabled' => true]);
        foreach ($warehouses as $warehouse) {
            $user->warehouseAccesses()->create([
                'warehouse_id' => $warehouse->id,
                'permissions' => ['view', 'transfers.request', 'transfers.approve'],
            ]);
        }
    }

    private function bin(Warehouse $warehouse, string $code): WarehouseLocation
    {
        return WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'code' => $code,
            'name' => 'Ubicación '.$code,
            'type' => WarehouseLocation::TYPE_BIN,
            'is_active' => true,
        ]);
    }

    private function preparedTransfer(int $quantity = 10): array
    {
        $source = Warehouse::primary();
        $destination = $this->warehouse('DEST-'.strtoupper(uniqid()));
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand(
            $product,
            $quantity,
            reason: 'Stock para transferencia',
            warehouse: $source,
        );
        $sourceLocation = $source->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $destinationLocation = $this->bin($destination, 'BIN-'.strtoupper(uniqid()));
        $requester = User::factory()->create();
        $approverOne = User::factory()->create();
        $approverTwo = User::factory()->create();
        foreach ([$requester, $approverOne, $approverTwo] as $actor) {
            $this->grantTransferAccess($actor, $source, $destination);
        }

        $transfer = app(WarehouseTransferService::class)->create(
            $source,
            $destination,
            [[
                'inventory_id' => $inventory->id,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'quantity' => $quantity,
            ]],
            $requester->id,
            'Reposición',
        );

        return compact('source', 'destination', 'inventory', 'sourceLocation', 'destinationLocation', 'requester', 'approverOne', 'approverTwo', 'transfer');
    }

    public function test_transfer_creation_requires_approval_and_does_not_move_stock(): void
    {
        $data = $this->preparedTransfer();

        $this->assertSame(WarehouseTransfer::STATUS_PENDING_APPROVAL, $data['transfer']->status);
        $this->assertSame(10, $data['inventory']->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('inventory_location_balances', [
            'inventory_id' => $data['inventory']->id,
            'warehouse_location_id' => $data['sourceLocation']->id,
            'quantity_on_hand' => 10,
        ]);
        $this->assertDatabaseHas('warehouse_transfer_events', [
            'warehouse_transfer_id' => $data['transfer']->id,
            'event' => 'created',
        ]);
    }

    public function test_rule_can_require_two_distinct_approvals_and_requester_cannot_approve(): void
    {
        $source = Warehouse::primary();
        $destination = $this->warehouse('APPROVAL-'.strtoupper(uniqid()));
        WarehouseTransferRule::query()->create([
            'name' => 'Doble aprobación',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'min_quantity' => 1,
            'max_quantity' => 100,
            'required_approvals' => 2,
            'auto_approve' => false,
            'requester_can_approve' => false,
            'priority' => 1,
            'is_active' => true,
        ]);

        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 10, warehouse: $source);
        $from = $source->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $to = $this->bin($destination, 'APPROVAL-BIN');
        $requester = User::factory()->create();
        $approverOne = User::factory()->create();
        $approverTwo = User::factory()->create();
        foreach ([$requester, $approverOne, $approverTwo] as $actor) {
            $this->grantTransferAccess($actor, $source, $destination);
        }
        $service = app(WarehouseTransferService::class);

        $transfer = $service->create($source, $destination, [[
            'inventory_id' => $inventory->id,
            'source_location_id' => $from->id,
            'destination_location_id' => $to->id,
            'quantity' => 5,
        ]], $requester->id);

        try {
            $service->approve($transfer, $requester->id);
            $this->fail('El solicitante no debe poder aprobar su propia transferencia.');
        } catch (ValidationException) {
            $this->assertSame(WarehouseTransfer::STATUS_PENDING_APPROVAL, $transfer->fresh()->status);
        }

        $service->approve($transfer, $approverOne->id);
        $this->assertSame(WarehouseTransfer::STATUS_PENDING_APPROVAL, $transfer->fresh()->status);
        $service->approve($transfer, $approverTwo->id);

        $this->assertSame(WarehouseTransfer::STATUS_APPROVED, $transfer->fresh()->status);
        $this->assertSame(2, $transfer->approvals()->where('decision', 'approved')->count());
    }

    public function test_dispatch_reduces_source_stock_and_receipt_accounts_for_good_and_damaged_units(): void
    {
        $data = $this->preparedTransfer();
        $service = app(WarehouseTransferService::class);
        $service->approve($data['transfer'], $data['approverOne']->id);

        $item = $data['transfer']->items()->firstOrFail();
        $service->dispatch($data['transfer'], [$item->id => 8], $data['approverOne']->id);

        $this->assertSame(WarehouseTransfer::STATUS_IN_TRANSIT, $data['transfer']->fresh()->status);
        $this->assertSame(2, $data['inventory']->fresh()->quantity_on_hand);

        $service->receive($data['transfer'], [
            $item->id => ['received' => 5, 'damaged' => 3],
        ], $data['approverTwo']->id);

        $transfer = $data['transfer']->fresh();
        $this->assertSame(WarehouseTransfer::STATUS_RECEIVED, $transfer->status);
        $this->assertSame(5, $transfer->items()->first()->quantity_received);
        $this->assertSame(3, $transfer->items()->first()->quantity_damaged);

        $destinationInventory = $transfer->items()->first()->destinationInventory;
        $this->assertSame(5, $destinationInventory->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $destinationInventory->id,
            'type' => 'transfer_receipt',
            'quantity_delta' => 5,
            'reference_type' => WarehouseTransfer::class,
            'reference_id' => $transfer->id,
        ]);
        $this->assertDatabaseHas('warehouse_transfer_events', [
            'warehouse_transfer_id' => $transfer->id,
            'event' => 'damage_reported',
        ]);
    }

    public function test_rejected_transfer_never_changes_inventory(): void
    {
        $data = $this->preparedTransfer();
        $service = app(WarehouseTransferService::class);
        $service->reject($data['transfer'], $data['approverOne']->id, 'No autorizado');

        $this->assertSame(WarehouseTransfer::STATUS_REJECTED, $data['transfer']->fresh()->status);
        $this->assertSame(10, $data['inventory']->fresh()->quantity_on_hand);
        $this->assertSame(0, $data['transfer']->items()->first()->quantity_dispatched);
    }

    public function test_replenishment_request_uses_existing_transfer_approval_flow_without_moving_stock(): void
    {
        $source = Warehouse::primary();
        $destination = $this->warehouse('RESTOCK-'.strtoupper(uniqid()));
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 15, reason: 'Stock disponible', warehouse: $source);
        $sourceLocation = $source->locations()->where('type', WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $destinationLocation = $this->bin($destination, 'RESTOCK-BIN-'.strtoupper(uniqid()));
        $requester = User::factory()->create();
        $this->grantTransferAccess($requester, $source, $destination);

        $transfer = app(WarehouseTransferService::class)->create(
            $source,
            $destination,
            [[
                'inventory_id' => $inventory->id,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'quantity' => 7,
            ]],
            $requester->id,
            'Reposición por umbral de stock bajo',
            'replenishment',
        );

        $this->assertSame('replenishment', $transfer->transfer_type);
        $this->assertSame(WarehouseTransfer::STATUS_PENDING_APPROVAL, $transfer->status);
        $this->assertSame(15, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(7, $transfer->items()->first()->quantity_requested);
    }

}
