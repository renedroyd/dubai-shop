<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\PosCashSession;
use App\Models\PosLocation;
use App\Models\PosRefund;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class PosLocationMetrics extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?int $navigationSort = 83;
    protected string $view = 'filament.pages.pos-location-metrics';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAdminPermission('pos_reports.view')
            && $user->warehouseIdsWithPermission('reports.view') !== [];
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('Punto de venta');
    }

    public static function getNavigationLabel(): string
    {
        return __('Indicadores por establecimiento');
    }

    public function getTitle(): string
    {
        return __('Indicadores por establecimiento');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user && static::canAccess(), 403);

        $warehouseIds = $user->warehouseIdsWithPermission('reports.view');
        [$from, $to] = $this->period();

        $locations = PosLocation::query()
            ->whereHas('terminals', fn (Builder $query) => $query->whereIn('warehouse_id', $warehouseIds))
            ->withCount([
                'terminals as active_terminals_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->whereIn('warehouse_id', $warehouseIds),
            ])
            ->orderBy('name')
            ->get();

        $locationIds = $locations->pluck('id');
        $sales = Order::query()
            ->join('pos_terminals', 'pos_terminals.id', '=', 'orders.pos_terminal_id')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereIn('pos_terminals.pos_location_id', $locationIds)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereIn('orders.payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->selectRaw('pos_terminals.pos_location_id, orders.currency, COUNT(orders.id) as orders_count, SUM(orders.total_amount) as sales_amount')
            ->groupBy('pos_terminals.pos_location_id', 'orders.currency')
            ->orderBy('pos_terminals.pos_location_id')
            ->get()
            ->groupBy('pos_location_id');

        $refunds = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->join('pos_terminals', 'pos_terminals.id', '=', 'orders.pos_terminal_id')
            ->where('pos_refunds.status', 'completed')
            ->where('orders.channel', 'pos')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereIn('pos_terminals.pos_location_id', $locationIds)
            ->whereBetween('pos_refunds.refunded_at', [$from, $to])
            ->selectRaw('pos_terminals.pos_location_id, pos_refunds.currency, COUNT(pos_refunds.id) as refunds_count, SUM(pos_refunds.amount) as refunded_amount')
            ->groupBy('pos_terminals.pos_location_id', 'pos_refunds.currency')
            ->get()
            ->groupBy('pos_location_id');

        $sessionCounts = PosCashSession::query()
            ->join('pos_terminals', 'pos_terminals.id', '=', 'pos_cash_sessions.pos_terminal_id')
            ->whereIn('pos_terminals.warehouse_id', $warehouseIds)
            ->whereIn('pos_terminals.pos_location_id', $locationIds)
            ->where(function (Builder $query) use ($from, $to): void {
                $query->whereBetween('pos_cash_sessions.opened_at', [$from, $to])
                    ->orWhereBetween('pos_cash_sessions.closed_at', [$from, $to]);
            })
            ->selectRaw('pos_terminals.pos_location_id, COUNT(*) as sessions_count, SUM(CASE WHEN pos_cash_sessions.status = ? AND pos_cash_sessions.closed_at IS NULL THEN 1 ELSE 0 END) as open_sessions_count, SUM(CASE WHEN pos_cash_sessions.close_requested_at IS NOT NULL AND pos_cash_sessions.closed_at IS NULL THEN 1 ELSE 0 END) as pending_close_count', [PosCashSession::STATUS_OPEN])
            ->groupBy('pos_terminals.pos_location_id')
            ->get()
            ->keyBy('pos_location_id');

        $currentSessions = PosCashSession::query()
            ->join('pos_terminals', 'pos_terminals.id', '=', 'pos_cash_sessions.pos_terminal_id')
            ->whereIn('pos_terminals.warehouse_id', $warehouseIds)
            ->whereIn('pos_terminals.pos_location_id', $locationIds)
            ->where('pos_cash_sessions.status', PosCashSession::STATUS_OPEN)
            ->whereNull('pos_cash_sessions.closed_at')
            ->selectRaw('pos_terminals.pos_location_id, COUNT(*) as open_sessions_count, SUM(CASE WHEN pos_cash_sessions.close_requested_at IS NOT NULL THEN 1 ELSE 0 END) as pending_close_count')
            ->groupBy('pos_terminals.pos_location_id')
            ->get()
            ->keyBy('pos_location_id');

        $metrics = $locations->map(function (PosLocation $location) use ($sales, $refunds, $sessionCounts, $currentSessions) {
            $locationSales = $sales->get($location->id, collect())->keyBy('currency');
            $locationRefunds = $refunds->get($location->id, collect())->keyBy('currency');
            $currencies = $locationSales->keys()->merge($locationRefunds->keys())->unique()->sort();

            return [
                'location' => $location,
                'currencyRows' => $currencies->map(fn ($currency) => [
                    'currency' => $currency,
                    'orders_count' => (int) ($locationSales->get($currency)?->orders_count ?? 0),
                    'sales_amount' => (int) ($locationSales->get($currency)?->sales_amount ?? 0),
                    'refunded_amount' => (int) ($locationRefunds->get($currency)?->refunded_amount ?? 0),
                ]),
                'sessions' => $sessionCounts->get($location->id),
                'currentSessions' => $currentSessions->get($location->id),
            ];
        });

        return [
            'metrics' => $metrics,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    private function period(): array
    {
        try {
            $from = Carbon::createFromFormat('!Y-m-d', (string) request()->query('from', now()->subDays(29)->toDateString()))->startOfDay();
            $to = Carbon::createFromFormat('!Y-m-d', (string) request()->query('to', now()->toDateString()))->endOfDay();
        } catch (\Throwable) {
            $from = now()->subDays(29)->startOfDay();
            $to = now()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365)->startOfDay();
        }

        return [$from, $to];
    }
}
