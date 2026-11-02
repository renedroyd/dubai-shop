<?php

namespace Tests\\Feature;

use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dashboard_displays_the_commercial_overview_and_widgets(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Pedidos de hoy')
            ->assertSee('Pagos por revisar')
            ->assertSee('Ventas cobradas este mes')
            ->assertSee('Productos con stock bajo')
            ->assertSee('Centro de atención')
            ->assertSee('Actividad de pedidos')
            ->assertSee('Pedidos por estado')
            ->assertSee('Métodos de pago')
            ->assertSee('Pedidos recientes')
            ->assertSee('Inventario que requiere atención')
            ->assertSee('Entregas fuera de plazo');
    }
}
