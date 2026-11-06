<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommerceOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAdminPermission('dashboard.view') ?? false;
    }

    protected function getStats(): array
    {
        $salesThisMonth = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('placed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('currency, SUM(total_amount) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row): string => number_format($row->total / 100, 2).' '.$row->currency)
            ->implode(' · ');

        return [
            Stat::make(__('Pedidos de hoy'), Order::query()->whereDate('placed_at', today())->count())
                ->description(__('Pedidos registrados hoy'))
                ->descriptionIcon('heroicon-m-shopping-bag'),
            Stat::make(__('Pagos por revisar'), Payment::query()->where('status', Payment::STATUS_AWAITING_REVIEW)->count())
                ->description(__('Comprobantes pendientes de validación'))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make(__('Ventas cobradas este mes'), ($salesThisMonth ?: '0.00'))
                ->description(__('Suma de pedidos marcados como pagados'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make(__('Productos con stock bajo'), Inventory::query()->whereRaw('(quantity_on_hand - quantity_reserved) <= 5')->count())
                ->description(__('Existencias disponibles de 5 unidades o menos'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
