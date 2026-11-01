<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommerceOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $salesThisMonth = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('placed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_amount');

        return [
            Stat::make('Pedidos de hoy', Order::query()->whereDate('placed_at', today())->count())
                ->description('Pedidos registrados hoy')
                ->descriptionIcon('heroicon-m-shopping-bag'),
            Stat::make('Pagos por revisar', Payment::query()->where('status', Payment::STATUS_AWAITING_REVIEW)->count())
                ->description('Comprobantes pendientes de validación')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Ventas cobradas este mes', number_format($salesThisMonth / 100, 2).' USD')
                ->description('Suma de pedidos marcados como pagados')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Productos con stock bajo', Inventory::query()->whereRaw('(quantity_on_hand - quantity_reserved) <= 5')->count())
                ->description('Existencias disponibles de 5 unidades o menos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
