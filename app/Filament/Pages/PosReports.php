<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\PosTerminal;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pos\PosReportService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosReports extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?int $navigationSort = 80;
    protected string $view = 'filament.pages.pos-reports';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAdminPermission('pos_reports.view')
            && $user->warehouseIdsWithPermission('reports.view') !== [];
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('Informes');
    }

    public static function getNavigationLabel(): string
    {
        return __('Informes POS y almacenes');
    }

    public function getTitle(): string
    {
        return __('Informes POS y almacenes');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $filters = $this->filtersFromRequest($user);
        $allowedWarehouseIds = $user->warehouseIdsWithPermission('reports.view');
        $terminals = PosTerminal::query()
            ->whereIn('warehouse_id', $allowedWarehouseIds)
            ->orderBy('name')->get(['id', 'name', 'code', 'warehouse_id']);

        $cashierIds = Order::query()
            ->where('channel', 'pos')
            ->whereIn('pos_warehouse_id', $allowedWarehouseIds)
            ->whereNotNull('cashier_id')
            ->distinct()->pluck('cashier_id');

        return array_merge(
            app(PosReportService::class)->build($user, $filters),
            [
                'warehouses' => Warehouse::query()->whereIn('id', $allowedWarehouseIds)->orderBy('name')->get(['id', 'name']),
                'terminals' => $terminals,
                'cashiers' => User::query()->whereIn('id', $cashierIds)->orderBy('name')->get(['id', 'name']),
                'filters' => $filters,
            ],
        );
    }

    public function exportCsv(
        string $from,
        string $to,
        ?int $warehouseId = null,
        ?int $terminalId = null,
        ?int $cashierId = null,
        ?string $currency = null,
    ): StreamedResponse {
        $user = auth()->user();
        abort_unless($user && static::canAccess(), 403);

        $filters = $this->sanitizeFilters($user, $from, $to, $warehouseId, $terminalId, $cashierId, $currency);
        $rows = app(PosReportService::class)->exportRows($user, $filters);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Pedido', 'Fecha', 'Almacén', 'Terminal', 'Cajero', 'Moneda',
                'Subtotal', 'Descuentos', 'Total', 'Reembolsos', 'Métodos de pago', 'Estado de pago',
            ]);

            foreach ($rows->with(['posWarehouse:id,name', 'posTerminal:id,name,code', 'cashier:id,name', 'payments.method', 'posRefunds'])->lazyById(500) as $order) {
                $refundTotal = $order->posRefunds->where('status', 'completed')->sum('amount');
                $paymentSummary = $order->payments
                    ->map(fn ($payment) => ($payment->method?->name ?? $payment->driver).': '.number_format($payment->amount / 100, 2).' '.$payment->currency)
                    ->implode(' | ');

                fputcsv($stream, [
                    $order->number,
                    $order->placed_at?->format('Y-m-d H:i:s'),
                    $order->posWarehouse?->name,
                    $order->posTerminal?->name,
                    $order->cashier?->name,
                    $order->currency,
                    number_format($order->subtotal_amount / 100, 2, '.', ''),
                    number_format($order->discount_amount / 100, 2, '.', ''),
                    number_format($order->total_amount / 100, 2, '.', ''),
                    number_format($refundTotal / 100, 2, '.', ''),
                    $paymentSummary,
                    $order->payment_status,
                ]);
            }

            fclose($stream);
        }, 'informe-pos-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filtersFromRequest(User $user): array
    {
        return $this->sanitizeFilters(
            $user,
            (string) request()->query('from', now()->subDays(29)->toDateString()),
            (string) request()->query('to', now()->toDateString()),
            request()->integer('warehouse_id') ?: null,
            request()->integer('terminal_id') ?: null,
            request()->integer('cashier_id') ?: null,
            request()->query('currency'),
        );
    }

    private function sanitizeFilters(
        User $user,
        string $from,
        string $to,
        ?int $warehouseId,
        ?int $terminalId,
        ?int $cashierId,
        ?string $currency,
    ): array {
        $allowedWarehouseIds = $user->warehouseIdsWithPermission('reports.view');
        abort_if($allowedWarehouseIds === [], 403);

        try {
            $fromDate = Carbon::createFromFormat('!Y-m-d', $from)->startOfDay();
            $toDate = Carbon::createFromFormat('!Y-m-d', $to)->endOfDay();
        } catch (\Throwable) {
            $fromDate = now()->subDays(29)->startOfDay();
            $toDate = now()->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        if ($fromDate->diffInDays($toDate) > 365) {
            $fromDate = $toDate->copy()->subDays(365)->startOfDay();
        }

        if (! $warehouseId || ! in_array($warehouseId, $allowedWarehouseIds, true)) {
            $warehouseId = null;
        }

        $terminalIds = PosTerminal::query()
            ->whereIn('warehouse_id', $warehouseId ? [$warehouseId] : $allowedWarehouseIds)
            ->pluck('id')->all();
        if (! $terminalId || ! in_array($terminalId, $terminalIds, true)) {
            $terminalId = null;
        }

        $cashierIds = Order::query()
            ->where('channel', 'pos')
            ->whereIn('pos_warehouse_id', $allowedWarehouseIds)
            ->whereNotNull('cashier_id')->distinct()->pluck('cashier_id')->all();
        if (! $cashierId || ! in_array($cashierId, $cashierIds, true)) {
            $cashierId = null;
        }

        if (! in_array($currency, ['CUP', 'USD', 'EUR'], true)) {
            $currency = null;
        }

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'warehouse_id' => $warehouseId,
            'terminal_id' => $terminalId,
            'cashier_id' => $cashierId,
            'currency' => $currency,
        ];
    }
}
