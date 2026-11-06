<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_warehouse_is_protected_from_deactivation_and_deletion_by_resource_configuration(): void
    {
        $warehouse = Warehouse::primary();

        $this->assertTrue($warehouse->is_primary);
        $this->assertTrue($warehouse->is_active);
        $this->assertSame('MAIN', $warehouse->code);
    }

    public function test_location_parent_must_belong_to_the_same_warehouse(): void
    {
        $primary = Warehouse::primary();
        $secondary = Warehouse::query()->create([
            'code' => 'SECONDARY',
            'name' => 'Almacén secundario',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
        ]);
        $parent = WarehouseLocation::query()->create([
            'warehouse_id' => $primary->id,
            'code' => 'AREA-A',
            'name' => 'Área A',
            'type' => WarehouseLocation::TYPE_AREA,
        ]);

        $this->expectException(ValidationException::class);

        WarehouseLocation::query()->create([
            'warehouse_id' => $secondary->id,
            'parent_id' => $parent->id,
            'code' => 'AISLE-A',
            'name' => 'Pasillo A',
            'type' => WarehouseLocation::TYPE_AISLE,
        ]);
    }

    public function test_location_hierarchy_cannot_create_a_cycle(): void
    {
        $warehouse = Warehouse::primary();
        $area = WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'CYCLE-A',
            'name' => 'Área de prueba',
            'type' => WarehouseLocation::TYPE_AREA,
        ]);
        $aisle = WarehouseLocation::query()->create([
            'warehouse_id' => $warehouse->id,
            'parent_id' => $area->id,
            'code' => 'CYCLE-B',
            'name' => 'Pasillo de prueba',
            'type' => WarehouseLocation::TYPE_AISLE,
        ]);

        $this->expectException(ValidationException::class);

        $area->update(['parent_id' => $aisle->id]);
    }
}
