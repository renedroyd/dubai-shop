<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalAlerts extends StatsOverviewWidget
{
    protected ?string $heading = 'Centro de atención';

    protected ?string $description = 'Pendientes que requieren seguimiento del equipo';

    protected function getStats(): array
    {
        $pendingPayments = Payment::query()
            ->where('status', Payment::STATUS_AWAITING_REVIEW)
            ->count();

        $pendingOrders = Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->count();

        $overdueDeliveries = Order::query()
            ->whereNotNull('delivery_due_at')
            ->where('delivery_due_at', '<', now())
            ->whereNotIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED])
            ->count();

        $lowStock = Inventory::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= 5')
            ->count();

        $failedNotifications = NotificationLog::query()
            ->where('status', 'failed')
            ->count();

        return [
            Stat::make('Pagos por validar', $pendingPayments)
                ->description('Comprobantes pendientes de revisión')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayments > 0 ? 'warning' : 'success'),
            Stat::make('Pedidos por confirmar', $pendingOrders)
                ->description('Pedidos que esperan confirmación')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color($pendingOrders > 0 ? 'warning' : 'success'),
            Stat::make('Entregas fuera de plazo', $overdueDeliveries)
                ->description('Pedidos que superaron su compromiso')
                ->descriptionIcon('heroicon-m-truck')
                ->color($overdueDeliveries > 0 ? 'danger' : 'success'),
            Stat::make('Stock bajo / agotado', $lowStock)
                ->description('Inventarios con 5 unidades disponibles o menos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'success'),
            Stat::make('Notificaciones fallidas', $failedNotifications)
                ->description('Envíos que requieren comprobación')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($failedNotifications > 0 ? 'danger' : 'success'),
        ];
    }
}
