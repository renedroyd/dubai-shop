<?php

namespace App\Filament\Resources\WarehouseTransfers;

use App\Filament\Resources\WarehouseTransfers\Pages\CreateWarehouseTransfer;
use App\Filament\Resources\WarehouseTransfers\Pages\ListWarehouseTransfers;
use App\Filament\Resources\WarehouseTransfers\Pages\ViewWarehouseTransfer;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseTransfer;
use App\Services\Inventory\WarehouseTransferService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehouseTransferResource extends Resource
{
    protected static ?string $model = WarehouseTransfer::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?int $navigationSort = 27;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Transferencias'); }
    public static function getModelLabel(): string { return __('transferencia'); }
    public static function getPluralModelLabel(): string { return __('transferencias'); }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()
            ->whereIn('source_warehouse_id', $warehouseIds)
            ->whereIn('destination_warehouse_id', $warehouseIds);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('source_warehouse_id')->label(__('Almacén de origen'))
                ->options(fn () => Warehouse::query()->where('is_active', true)->whereIn('id', auth()->user()?->warehouseIdsWithPermission('transfers.request') ?? [])->pluck('name', 'id'))
                ->searchable()->required(),
            Select::make('destination_warehouse_id')->label(__('Almacén de destino'))
                ->options(fn () => Warehouse::query()->where('is_active', true)->pluck('name', 'id'))
                ->searchable()->different('source_warehouse_id')->required(),
            Select::make('transfer_type')->label(__('Tipo de transferencia'))->options([
                'standard' => __('Estándar'),
                'replenishment' => __('Reposición'),
                'return' => __('Devolución interna'),
            ])->default('standard')->required(),
            Textarea::make('notes')->label(__('Notas'))->rows(2),
            Repeater::make('items')->label(__('Productos a transferir'))->minItems(1)->schema([
                Select::make('inventory_id')->label(__('Producto / variante'))
                    ->options(fn () => Inventory::query()->whereIn('warehouse_id', auth()->user()?->warehouseIdsWithPermission('transfers.request') ?? [])->with('product')->get()->mapWithKeys(fn (Inventory $inventory) => [
                        $inventory->id => ($inventory->product?->name ?? $inventory->stock_key).' — '.$inventory->available_quantity.' '.__('disponibles'),
                    ]))->searchable()->required(),
                Select::make('source_location_id')->label(__('Ubicación de origen'))
                    ->options(fn () => WarehouseLocation::query()->where('is_active', true)->whereIn('warehouse_id', auth()->user()?->warehouseIdsWithPermission('transfers.request') ?? [])->get()->mapWithKeys(fn (WarehouseLocation $location) => [
                        $location->id => $location->warehouse->name.' / '.$location->code.' — '.$location->name,
                    ]))->searchable()->required(),
                Select::make('destination_location_id')->label(__('Ubicación de destino'))
                    ->options(fn () => WarehouseLocation::query()->where('is_active', true)->where('type', '!=', WarehouseLocation::TYPE_UNASSIGNED)->get()->mapWithKeys(fn (WarehouseLocation $location) => [
                        $location->id => $location->warehouse->name.' / '.$location->code.' — '.$location->name,
                    ]))->searchable()->required(),
                TextInput::make('quantity')->label(__('Cantidad solicitada'))->numeric()->integer()->minValue(1)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('Código'))->searchable()->sortable(),
            TextColumn::make('sourceWarehouse.name')->label(__('Origen')),
            TextColumn::make('destinationWarehouse.name')->label(__('Destino')),
            TextColumn::make('transfer_type')->label(__('Tipo'))->badge(),
            TextColumn::make('status')->label(__('Estado'))->badge(),
            TextColumn::make('requested_at')->label(__('Solicitada'))->dateTime()->sortable(),
            TextColumn::make('requester.name')->label(__('Solicitante')),
        ])->filters([
            SelectFilter::make('status')->options([
                WarehouseTransfer::STATUS_PENDING_APPROVAL => __('Pendiente de aprobación'),
                WarehouseTransfer::STATUS_APPROVED => __('Aprobada'),
                WarehouseTransfer::STATUS_REJECTED => __('Rechazada'),
                WarehouseTransfer::STATUS_IN_TRANSIT => __('En tránsito'),
                WarehouseTransfer::STATUS_PARTIALLY_RECEIVED => __('Recepción parcial'),
                WarehouseTransfer::STATUS_RECEIVED => __('Recibida'),
            ]),
        ])->recordActions([
            ViewAction::make(),
            Action::make('approve')->label(__('Aprobar'))->color('success')
                ->form([Textarea::make('comments')->label(__('Comentarios'))->rows(2)])
                ->visible(fn (WarehouseTransfer $record): bool => $record->status === WarehouseTransfer::STATUS_PENDING_APPROVAL)
                ->action(fn (WarehouseTransfer $record, array $data) => app(WarehouseTransferService::class)->approve($record, auth()->id(), $data['comments'] ?? null)),
            Action::make('reject')->label(__('Rechazar'))->color('danger')
                ->form([Textarea::make('reason')->label(__('Motivo'))->required()->rows(2)])
                ->visible(fn (WarehouseTransfer $record): bool => $record->status === WarehouseTransfer::STATUS_PENDING_APPROVAL)
                ->action(fn (WarehouseTransfer $record, array $data) => app(WarehouseTransferService::class)->reject($record, auth()->id(), $data['reason'])),
            Action::make('dispatch')->label(__('Despachar'))->color('warning')
                ->form(fn (WarehouseTransfer $record): array => $record->items->map(fn ($item) => TextInput::make('quantities.'.$item->id)
                    ->label($item->sourceInventory?->product?->name.' — '.$item->quantity_requested)
                    ->numeric()->integer()->minValue(0)->maxValue($item->quantity_requested)->default(0))->all())
                ->visible(fn (WarehouseTransfer $record): bool => $record->status === WarehouseTransfer::STATUS_APPROVED)
                ->action(fn (WarehouseTransfer $record, array $data) => app(WarehouseTransferService::class)->dispatch($record, $data['quantities'] ?? [], auth()->id())),
            Action::make('receive')->label(__('Registrar recepción'))->color('success')
                ->form(fn (WarehouseTransfer $record): array => $record->items->map(fn ($item) => [
                    TextInput::make('quantities.'.$item->id.'.received')->label(($item->sourceInventory?->product?->name ?? __('Producto')).' — '.__('Buenas'))->numeric()->integer()->minValue(0)->maxValue($item->quantity_dispatched - $item->quantity_received - $item->quantity_damaged)->default(0),
                    TextInput::make('quantities.'.$item->id.'.damaged')->label(__('Dañadas'))->numeric()->integer()->minValue(0)->maxValue($item->quantity_dispatched - $item->quantity_received - $item->quantity_damaged)->default(0),
                ])->flatten()->all())
                ->visible(fn (WarehouseTransfer $record): bool => in_array($record->status, [WarehouseTransfer::STATUS_IN_TRANSIT, WarehouseTransfer::STATUS_PARTIALLY_RECEIVED], true))
                ->action(fn (WarehouseTransfer $record, array $data) => app(WarehouseTransferService::class)->receive($record, $data['quantities'] ?? [], auth()->id())),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseTransfers::route('/'),
            'create' => CreateWarehouseTransfer::route('/create'),
            'view' => ViewWarehouseTransfer::route('/{record}'),
        ];
    }
}
