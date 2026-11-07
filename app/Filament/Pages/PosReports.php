<?php

namespace App\Filament\Pages;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PosCashSession;
use App\Models\PosRefund;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

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
        $allowedWarehouseIds = $user?->warehouseIdsWithPermission('reports.view') ?? [];
        $requestedWarehouseId = request()->integer('warehouse_id') ?: null;
        $warehouseIds = $requestedWarehouseId && in_array($requestedWarehouseId, $allowedWarehouseIds, true)
            ? [$requestedWarehouseId]
            : $allowedWarehouseIds;

        $from = request()->query('from', now()->subDays(29)->toDateString());
        $to = request()->query('to', now()->toDateString());

        try {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            $fromDate = now()->subDays(29)->startOfDay();
            $toDate = now()->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        $sales = Order::query()
            ->where('channel', 'pos')
            ->whereIn('pos_warehouse_id', $warehouseIds)
            ->whereBetween('placed_at', [$fromDate, $toDate])
            ->selectRaw('pos_warehouse_id, currency, COUNT(*) as orders_count, SUM(total_amount) as total_amount')
            ->groupBy('pos_warehouse_id', 'currency')
            ->with('posWarehouse:id,name')
            ->orderBy('pos_warehouse_id')
            ->get();

        $refunds = PosRefund::query()
            ->join('orders', 'orders.id', '=', 'pos_refunds.order_id')
            ->join('warehouses', 'warehouses.id', '=', 'orders.pos_warehouse_id')
            ->where('pos_refunds.status', 'completed')
            ->whereIn('orders.pos_warehouse_id', $warehouseIds)
            ->whereBetween('pos_refunds.refunded_at', [$fromDate, $toDate])
            ->selectRaw('orders.pos_warehouse_id, warehouses.name as warehouse_name, pos_refunds.currency, COUNT(pos_refunds.id) as refunds_count, SUM(pos_refunds.amount) as total_amount')
            ->groupBy('orders.pos_warehouse_id', 'warehouses.name', 'pos_refunds.currency')
            ->orderBy('orders.pos_warehouse_id')
            ->get();

        $sessions = PosCashSession::query()
            ->whereHas('terminal', fn (Builder $query) => $query->whereIn('warehouse_id', $warehouseIds))
            ->whereBetween('opened_at', [$fromDate, $toDate])
            ->with('terminal.warehouse:id,name')
            ->orderByDesc('opened_at')
            ->get();

        $movements = InventoryMovement::query()
            ->whereHas('inventory', fn (Builder $query) => $query->whereIn('warehouse_id', $warehouseIds))
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->selectRaw('inventory_id, type, SUM(quantity_delta) as quantity_delta, COUNT(*) as movements_count')
            ->groupBy('inventory_id', 'type')
            ->with('inventory.warehouse:id,name', 'inventory.product:id,name', 'inventory.variant:id,name')
            ->orderBy('type')
            ->get();

        $transfers = WarehouseTransfer::query()
            ->whereIn('source_warehouse_id', $warehouseIds)
            ->whereIn('destination_warehouse_id', $warehouseIds)
            ->whereBetween('requested_at', [$fromDate, $toDate])
            ->selectRaw('status, COUNT(*) as transfers_count')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return [
            'warehouses' => Warehouse::query()->whereIn('id', $allowedWarehouseIds)->orderBy('name')->get(['id', 'name']),
            'selectedWarehouseId' => $requestedWarehouseId && in_array($requestedWarehouseId, $allowedWarehouseIds, true) ? $requestedWarehouseId : null,
            'fromDate' => $fromDate->toDateString(),
            'toDate' => $toDate->toDateString(),
            'sales' => $sales,
            'refunds' => $refunds,
            'sessions' => $sessions,
            'movements' => $movements,
            'transfers' => $transfers,
        ];
    }
}
