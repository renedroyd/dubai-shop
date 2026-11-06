<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CommerceOverview;
use App\Filament\Widgets\DeliveryAttention;
use App\Filament\Widgets\LowStockInventory;
use App\Filament\Widgets\OperationalAlerts;
use App\Filament\Widgets\OrderActivityChart;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\PaymentMethodsChart;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\SalesTrendChart;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_dashboard_with_all_commercial_widgets_registered(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create(['is_admin' => true, 'admin_access_enabled' => true]);
        $admin->roles()->attach(AdminRole::query()->where('slug', 'superadmin')->firstOrFail());

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();

        $widgets = app(Dashboard::class)->getWidgets();

        foreach ([
            CommerceOverview::class,
            OperationalAlerts::class,
            SalesTrendChart::class,
            OrderActivityChart::class,
            OrderStatusChart::class,
            PaymentMethodsChart::class,
            LowStockInventory::class,
            DeliveryAttention::class,
            RecentOrders::class,
        ] as $widget) {
            $this->assertContains($widget, $widgets);
        }
    }
}
