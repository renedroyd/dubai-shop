<?php

namespace App\Filament\Resources\WarehouseTransferRules;

use App\Filament\Resources\WarehouseTransferRules\Pages\CreateWarehouseTransferRule;
use App\Filament\Resources\WarehouseTransferRules\Pages\EditWarehouseTransferRule;
use App\Filament\Resources\WarehouseTransferRules\Pages\ListWarehouseTransferRules;
use App\Models\Warehouse;
use App\Models\WarehouseTransferRule;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehouseTransferRuleResource extends Resource
{
    protected static ?string $model = WarehouseTransferRule::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 28;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Inventario'); }
    public static function getNavigationLabel(): string { return __('Reglas de transferencia'); }
    public static function getModelLabel(): string { return __('regla de transferencia'); }
    public static function getPluralModelLabel(): string { return __('reglas de transferencia'); }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('warehouse.manage') ?? [];
        $allWarehouseIds = Warehouse::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $canManageAll = array_diff($allWarehouseIds, $warehouseIds) === [];

        return parent::getEloquentQuery()->where(function ($query) use ($warehouseIds, $canManageAll): void {
            $query->where(function ($specific) use ($warehouseIds): void {
                $specific->whereNotNull('source_warehouse_id')
                    ->whereNotNull('destination_warehouse_id')
                    ->whereIn('source_warehouse_id', $warehouseIds)
                    ->whereIn('destination_warehouse_id', $warehouseIds);
            });

            if ($canManageAll) {
                $query->orWhere(fn ($global) => $global->whereNull('source_warehouse_id')->orWhereNull('destination_warehouse_id'));
            }
        });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('Nombre de la regla'))->required()->maxLength(255),
            Select::make('source_warehouse_id')->label(__('Almacén de origen'))->options(fn () => Warehouse::query()->whereIn('id', auth()->user()?->warehouseIdsWithPermission('warehouse.manage') ?? [])->pluck('name', 'id'))->searchable()->placeholder(__('Cualquier almacén')),
            Select::make('destination_warehouse_id')->label(__('Almacén de destino'))->options(fn () => Warehouse::query()->whereIn('id', auth()->user()?->warehouseIdsWithPermission('warehouse.manage') ?? [])->pluck('name', 'id'))->searchable()->placeholder(__('Cualquier almacén')),
            Select::make('transfer_type')->label(__('Tipo de transferencia'))->options([
                'standard' => __('Estándar'),
                'replenishment' => __('Reposición'),
                'return' => __('Devolución interna'),
            ])->placeholder(__('Cualquier tipo')),
            Select::make('requester_role_slugs')->label(__('Roles que pueden solicitar'))->multiple()->options(fn () => \App\Models\AdminRole::query()->pluck('name', 'slug'))->placeholder(__('Cualquier rol')),
            Select::make('approver_role_slugs')->label(__('Roles autorizados para aprobar'))->multiple()->options(fn () => \App\Models\AdminRole::query()->pluck('name', 'slug'))->placeholder(__('Cualquier rol con permiso')),
            TextInput::make('min_quantity')->label(__('Cantidad mínima'))->numeric()->integer()->minValue(1),
            TextInput::make('max_quantity')->label(__('Cantidad máxima'))->numeric()->integer()->minValue(1),
            TextInput::make('required_approvals')->label(__('Aprobaciones requeridas'))->numeric()->integer()->minValue(1)->maxValue(10)->default(1)->required(),
            Toggle::make('auto_approve')->label(__('Aprobación automática'))->default(false),
            Toggle::make('requester_can_approve')->label(__('Permitir que el solicitante apruebe'))->default(false),
            TextInput::make('priority')->label(__('Prioridad (menor número primero)'))->numeric()->integer()->minValue(1)->default(100)->required(),
            Toggle::make('is_active')->label(__('Regla activa'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Regla'))->searchable(),
            TextColumn::make('sourceWarehouse.name')->label(__('Origen'))->placeholder(__('Cualquiera')),
            TextColumn::make('destinationWarehouse.name')->label(__('Destino'))->placeholder(__('Cualquiera')),
            TextColumn::make('transfer_type')->label(__('Tipo'))->badge()->placeholder(__('Todos')),
            TextColumn::make('required_approvals')->label(__('Aprobaciones')),
            IconColumn::make('auto_approve')->label(__('Automática'))->boolean(),
            IconColumn::make('is_active')->label(__('Activa'))->boolean(),
            TextColumn::make('priority')->label(__('Prioridad'))->sortable(),
        ])->recordActions([EditAction::make(), DeleteAction::make()->requiresConfirmation()])
          ->headerActions([CreateAction::make()->label(__('Nueva regla'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouseTransferRules::route('/'),
            'create' => CreateWarehouseTransferRule::route('/create'),
            'edit' => EditWarehouseTransferRule::route('/{record}/edit'),
        ];
    }
}
