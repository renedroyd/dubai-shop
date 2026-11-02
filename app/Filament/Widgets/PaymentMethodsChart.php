<?php

namespace App\\Filament\\Widgets;

use App\\Models\\Payment;
use Filament\\Widgets\\ChartWidget;
use Illuminate\\Support\\Facades\\DB;

class PaymentMethodsChart extends ChartWidget
{
    protected ?string $heading = 'Métodos de pago';

    protected ?string $description = 'Pagos registrados según el método utilizado';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $methods = Payment::query()
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->selectRaw('COALESCE(payment_methods.name, payments.driver) as method_name, COUNT(payments.id) as total')
            ->groupBy('payment_methods.name', 'payments.driver')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [[
                'data' => $methods->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => ['#C9A227', '#E5C76B', '#8C6D1F', '#64748B', '#4D9B78', '#B85C5C'],
                'borderWidth' => 0,
            ]],
            'labels' => $methods->pluck('method_name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
