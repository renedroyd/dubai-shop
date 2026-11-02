<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChart extends ChartWidget
{
    protected ?string $heading = 'Pedidos por estado';

    protected ?string $description = 'Distribución de todos los pedidos registrados';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $statuses = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $labels = [
            'pending' => 'Pendientes',
            'confirmed' => 'Confirmados',
            'processing' => 'En preparación',
            'shipped' => 'En camino',
            'delivered' => 'Entregados',
            'cancelled' => 'Cancelados',
        ];

        return [
            'datasets' => [[
                'data' => $statuses->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => ['#C9A227', '#E5C76B', '#8C6D1F', '#64748B', '#4D9B78', '#B85C5C'],
                'borderWidth' => 0,
            ]],
            'labels' => $statuses->map(fn ($row): string => $labels[$row->status] ?? ucfirst($row->status))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
