<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderActivityChart extends ChartWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAdminPermission('orders.view') ?? false;
    }

    public function getHeading(): ?string { return __('Actividad de pedidos'); }

    public function getDescription(): ?string { return __('Pedidos registrados durante los últimos 14 días'); }

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();

        $orders = Order::query()
            ->whereBetween('placed_at', [$start, $end])
            ->selectRaw('DATE(placed_at) as order_date, COUNT(*) as total')
            ->groupBy('order_date')
            ->pluck('total', 'order_date');

        $labels = collect(range(13, 0))
            ->map(fn (int $days): string => now()->subDays($days)->format('d/m'))
            ->values();

        $values = collect(range(13, 0))
            ->map(fn (int $days): int => (int) ($orders[now()->subDays($days)->toDateString()] ?? 0))
            ->values();

        return [
            'datasets' => [[
                'label' => __('Pedidos'),
                'data' => $values->all(),
                'borderColor' => '#C9A227',
                'backgroundColor' => 'rgba(201, 162, 39, 0.16)',
                'fill' => true,
                'tension' => 0.35,
            ]],
            'labels' => $labels->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
