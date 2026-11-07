<?php

namespace Tests\Feature;

use App\Filament\Pages\PosTransferTracking;
use App\Models\AdminRole;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\WarehouseTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTransferTrackingTest extends TestCase
{
    use RefreshDatabase;

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

    private function grantAccess(User $user, Warehouse ...$warehouses): void
    {
        $user->update(['admin_access_enabled' => true]);

        foreach ($warehouses as $warehouse) {
            $user->warehouseAccesses()->create([
                'warehouse_id' => $warehouse->id,
                'permissions' => WarehouseUserAccess::PERMISSIONS,
            ]);
        }
    }

    private function grantReportPermission(User $user): void
    {
        $role = AdminRole::query()->create([
            'name' => 'Consulta POS',
            'slug' => 'pos-transfer-report-'.uniqid(),
            'permissions' => ['pos_reports.view'],
        ]);

        $user->roles()->attach($role);
    }

    private function createTransfer(User $requester, Warehouse $source, Warehouse $destination): void
    {
        $product = \App\Models\Product::query()->create([
            'name' => 'Producto seguimiento',
            'slug' => 'producto-seguimiento-'.uniqid(),
            'sku' => 'TRACK-'.strtoupper(uniqid()),
            'price_amount' => 1200,
            'currency' => 'CUP',
            'status' => \App\Models\Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);

        $inventory = app(InventoryService::class)->setOnHand(
            $product,
            8,
            reason: 'Stock de prueba',
            warehouse: $source,
        );
        $sourceLocation = $source->locations()->where('type', \App\Models\WarehouseLocation::TYPE_UNASSIGNED)->firstOrFail();
        $destinationLocation = $destination->locations()->where('type', '!=', \App\Models\WarehouseLocation::TYPE_UNASSIGNED)->first();

        if (! $destinationLocation) {
            $destinationLocation = $destination->locations()->create([
                'code' => 'TRACK-BIN',
                'name' => 'Ubicación de recepción',
                'type' => \App\Models\WarehouseLocation::TYPE_BIN,
                'is_active' => true,
            ]);
        }

        app(WarehouseTransferService::class)->create(
            $source,
            $destination,
            [[
                'inventory_id' => $inventory->id,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'quantity' => 4,
            ]],
            $requester->id,
            'Reposición de prueba',
            'replenishment',
        );
    }

    public function test_authorized_user_can_view_transfer_tracking_and_incident_dashboard(): void
    {
        $source = Warehouse::primary();
        $destination = $this->warehouse('TRACK-DEST');
        $user = User::factory()->create();
        $this->grantAccess($user, $source, $destination);
        $this->grantReportPermission($user);
        $this->createTransfer($user, $source, $destination);

        Livewire::actingAs($user)
            ->test(PosTransferTracking::class)
            ->assertSee('Seguimiento de transferencias')
            ->assertSee('Actividad reciente')
            ->assertSee('TRF-')
            ->assertSee('Reposición');
    }

    public function test_transfer_tracking_does_not_expose_transfers_if_user_lacks_access_to_either_warehouse(): void
    {
        $source = Warehouse::primary();
        $destination = $this->warehouse('TRACK-PRIVATE');
        $requester = User::factory()->create();
        $this->grantAccess($requester, $source, $destination);
        $this->createTransfer($requester, $source, $destination);

        $viewer = User::factory()->create();
        $this->grantAccess($viewer, $source);
        $this->grantReportPermission($viewer);

        Livewire::actingAs($viewer)
            ->test(PosTransferTracking::class)
            ->assertDontSee('TRF-')
            ->assertSee('No hay transferencias en el ámbito de almacenes autorizado.');
    }
}
