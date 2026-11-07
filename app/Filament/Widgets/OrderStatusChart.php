<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChart extends ChartWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAdminPermission('orders.view') ?? false;
    }

    public function getHeading(): ?string { return __('Pedidos por estado'); }

    public function getDescription(): ?string { return __('Distribución de todos los pedidos registrados'); }

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $statuses = Order::query()->accessibleTo(auth()->user())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $labels = [
            Order::STATUS_PENDING => __('Pendientes'),
            Order::STATUS_CONFIRMED => __('Confirmados'),
            Order::STATUS_PROCESSING => __('En preparación'),
            Order::STATUS_SHIPPED => __('En camino'),
            Order::STATUS_DELIVERED => __('Entregados'),
            Order::STATUS_CANCELLED => __('Cancelados'),
        ];

        $colors = [
            Order::STATUS_PENDING => '#C9A227',
            Order::STATUS_CONFIRMED => '#E5C76B',
            Order::STATUS_PROCESSING => '#8C6D1F',
            Order::STATUS_SHIPPED => '#64748B',
            Order::STATUS_DELIVERED => '#4D9B78',
            Order::STATUS_CANCELLED => '#B85C5C',
        ];

        return [
            'datasets' => [[
                'data' => $statuses->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => $statuses
                    ->map(fn ($row): string => $colors[$row->status] ?? '#A6A6A6')
                    ->all(),
                'borderWidth' => 0,
            ]],
            'labels' => $statuses
                ->map(fn ($row): string => $labels[$row->status] ?? ucfirst($row->status))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
