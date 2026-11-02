<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesTrendChart extends ChartWidget
{
    protected ?string $heading = 'Evolución de ventas cobradas';

    protected ?string $description = 'Ingresos diarios de los últimos 30 días, separados por moneda';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $sales = Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('placed_at', [$start, $end])
            ->selectRaw('currency, DATE(placed_at) as sale_date, SUM(total_amount) as total')
            ->groupBy('currency', 'sale_date')
            ->orderBy('sale_date')
            ->get()
            ->groupBy('currency');

        $dates = collect(range(29, 0))
            ->map(fn (int $days): string => now()->subDays($days)->toDateString());

        $palette = ['#C9A227', '#E5C76B', '#8C6D1F', '#4D9B78', '#64748B', '#B85C5C'];
        $currencies = $sales->keys()->values();

        $datasets = $sales->map(function ($rows, string $currency) use ($dates, $palette, $currencies): array {
            $totals = $rows->pluck('total', 'sale_date');
            $currencyIndex = $currencies->search($currency);

            return [
                'label' => $currency,
                'data' => $dates
                    ->map(fn (string $date): float => round(((int) ($totals[$date] ?? 0)) / 100, 2))
                    ->all(),
                'borderColor' => $palette[$currencyIndex % count($palette)],
                'backgroundColor' => 'transparent',
                'fill' => false,
                'tension' => 0.35,
                'borderWidth' => 2,
            ];
        })->values()->all();

        return [
            'datasets' => $datasets,
            'labels' => $dates
                ->map(fn (string $date): string => Carbon::parse($date)->format('d/m'))
                ->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
