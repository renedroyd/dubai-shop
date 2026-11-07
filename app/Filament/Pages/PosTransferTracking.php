<?php

namespace App\Filament\Pages;

use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferEvent;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class PosTransferTracking extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 82;
    protected string $view = 'filament.pages.pos-transfer-tracking';

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
        return __('Seguimiento de transferencias');
    }

    public function getTitle(): string
    {
        return __('Seguimiento de transferencias');
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        abort_unless($user && static::canAccess(), 403);

        $warehouseIds = $user->warehouseIdsWithPermission('reports.view');

        $transfers = WarehouseTransfer::query()
            ->whereIn('source_warehouse_id', $warehouseIds)
            ->whereIn('destination_warehouse_id', $warehouseIds);

        $statusCounts = (clone $transfers)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentTransfers = (clone $transfers)
            ->with([
                'sourceWarehouse:id,name',
                'destinationWarehouse:id,name',
                'requester:id,name',
                'items.sourceInventory.product:id,name',
                'events' => fn ($query) => $query->with('user:id,name')->latest()->limit(5),
            ])
            ->orderByDesc('requested_at')
            ->limit(30)
            ->get();

        $incidentEvents = WarehouseTransferEvent::query()
            ->whereIn('event', ['damage_reported', 'rejected'])
            ->whereHas('transfer', fn (Builder $query) => $query
                ->whereIn('source_warehouse_id', $warehouseIds)
                ->whereIn('destination_warehouse_id', $warehouseIds))
            ->with(['transfer:id,code,source_warehouse_id,destination_warehouse_id,status', 'transfer.sourceWarehouse:id,name', 'transfer.destinationWarehouse:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $warehouseMetrics = Warehouse::query()
            ->whereIn('id', $warehouseIds)
            ->withCount([
                'outgoingTransfers as outgoing_transfer_count' => fn (Builder $query) => $query->whereIn('destination_warehouse_id', $warehouseIds),
                'incomingTransfers as incoming_transfer_count' => fn (Builder $query) => $query->whereIn('source_warehouse_id', $warehouseIds),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return [
            'statusCounts' => $statusCounts,
            'recentTransfers' => $recentTransfers,
            'incidentEvents' => $incidentEvents,
            'warehouseMetrics' => $warehouseMetrics,
            'activeCount' => (int) $statusCounts->only([
                WarehouseTransfer::STATUS_PENDING_APPROVAL,
                WarehouseTransfer::STATUS_APPROVED,
                WarehouseTransfer::STATUS_IN_TRANSIT,
                WarehouseTransfer::STATUS_PARTIALLY_RECEIVED,
            ])->sum(),
            'inTransitCount' => (int) ($statusCounts[WarehouseTransfer::STATUS_IN_TRANSIT] ?? 0)
                + (int) ($statusCounts[WarehouseTransfer::STATUS_PARTIALLY_RECEIVED] ?? 0),
        ];
    }
}
