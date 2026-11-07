<?php

namespace Tests\Feature;

use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosEstablishmentAndTerminalTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_is_assigned_to_an_active_establishment_and_warehouse(): void
    {
        $location = PosLocation::query()->create([
            'code' => 'GTMO-01',
            'name' => 'Tienda Guantánamo',
            'address' => 'Guantánamo',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::primary();

        $terminal = PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'CAJA-01',
            'name' => 'Caja principal',
            'is_active' => true,
        ]);

        $this->assertSame($location->id, $terminal->location->id);
        $this->assertSame($warehouse->id, $terminal->warehouse->id);
        $this->assertSame(1, $location->terminals()->count());
        $this->assertSame(1, $warehouse->posTerminals()->count());
    }

    public function test_active_terminal_cannot_be_assigned_to_inactive_warehouse(): void
    {
        $location = PosLocation::query()->create([
            'code' => 'GTMO-02',
            'name' => 'Sucursal',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::query()->create([
            'code' => 'CLOSED-WH',
            'name' => 'Almacén cerrado',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);

        PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'CAJA-02',
            'name' => 'Caja inactiva',
            'is_active' => true,
        ]);
    }

    public function test_active_establishment_cannot_be_deactivated_while_it_has_active_terminals(): void
    {
        $location = PosLocation::query()->create([
            'code' => 'GTMO-03',
            'name' => 'Sucursal con caja',
            'is_active' => true,
        ]);
        PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'CAJA-03',
            'name' => 'Caja activa',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $location->update(['is_active' => false]);
    }

    public function test_terminal_code_is_unique_inside_an_establishment_but_not_globally(): void
    {
        $first = PosLocation::query()->create(['code' => 'LOC-A', 'name' => 'Sucursal A', 'is_active' => true]);
        $second = PosLocation::query()->create(['code' => 'LOC-B', 'name' => 'Sucursal B', 'is_active' => true]);

        foreach ([$first, $second] as $location) {
            PosTerminal::query()->create([
                'pos_location_id' => $location->id,
                'warehouse_id' => Warehouse::primary()->id,
                'code' => 'CAJA-01',
                'name' => 'Caja 1',
                'is_active' => true,
            ]);
        }

        $this->assertSame(2, PosTerminal::query()->where('code', 'CAJA-01')->count());

        $this->expectException(QueryException::class);

        PosTerminal::query()->create([
            'pos_location_id' => $first->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'CAJA-01',
            'name' => 'Caja duplicada',
            'is_active' => true,
        ]);
    }
    public function test_warehouse_cannot_be_deactivated_while_assigned_to_an_active_terminal(): void
    {
        $warehouse = Warehouse::primary();
        $location = PosLocation::query()->create([
            'code' => 'GTMO-04',
            'name' => 'Sucursal con almacén',
            'is_active' => true,
        ]);
        PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'CAJA-04',
            'name' => 'Caja activa',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $warehouse->update(['is_active' => false]);
    }

}
