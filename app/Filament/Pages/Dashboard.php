<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CommerceOverview;
use App\Filament\Widgets\DeliveryAttention;
use App\Filament\Widgets\LowStockInventory;
use App\Filament\Widgets\OperationalAlerts;
use App\Filament\Widgets\OrderActivityChart;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\PaymentMethodsChart;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\SalesTrendChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            CommerceOverview::class,
            OperationalAlerts::class,
            SalesTrendChart::class,
            OrderActivityChart::class,
            OrderStatusChart::class,
            PaymentMethodsChart::class,
            LowStockInventory::class,
            DeliveryAttention::class,
            RecentOrders::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'xl' => 4,
        ];
    }
}
