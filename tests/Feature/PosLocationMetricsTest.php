<?php

namespace Tests\Feature;

use App\Filament\Pages\PosLocationMetrics;
use App\Models\AdminRole;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosLocationMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_establishments_with_report_access_to_their_warehouses(): void
    {
        $allowedWarehouse = Warehouse::primary();
        $privateWarehouse = Warehouse::query()->create([
            'code' => 'LOC-PRIVATE',
            'name' => 'Almacén privado',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
        ]);
        $allowedLocation = PosLocation::query()->create([
            'code' => 'LOC-ALLOWED',
            'name' => 'Establecimiento autorizado',
            'is_active' => true,
        ]);
        $privateLocation = PosLocation::query()->create([
            'code' => 'LOC-HIDDEN',
            'name' => 'Establecimiento restringido',
            'is_active' => true,
        ]);
        PosTerminal::query()->create([
            'pos_location_id' => $allowedLocation->id,
            'warehouse_id' => $allowedWarehouse->id,
            'code' => 'TERM-ALLOWED',
            'name' => 'Terminal autorizado',
            'is_active' => true,
        ]);
        PosTerminal::query()->create([
            'pos_location_id' => $privateLocation->id,
            'warehouse_id' => $privateWarehouse->id,
            'code' => 'TERM-HIDDEN',
            'name' => 'Terminal privado',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Consulta indicadores POS',
            'slug' => 'pos-location-metrics-'.uniqid(),
            'permissions' => ['panel.access', 'pos_reports.view'],
        ]);
        $user->roles()->attach($role);
        $user->warehouseAccesses()->create([
            'warehouse_id' => $allowedWarehouse->id,
            'permissions' => ['view', 'reports.view'],
        ]);

        Livewire::actingAs($user)
            ->test(PosLocationMetrics::class)
            ->assertSee('Indicadores por establecimiento')
            ->assertSee('Establecimiento autorizado')
            ->assertDontSee('Establecimiento restringido');
    }
}
