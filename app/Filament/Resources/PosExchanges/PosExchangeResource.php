<?php

namespace App\Filament\Resources\PosExchanges;

use App\Filament\Resources\PosExchanges\Pages\ListPosExchanges;
use App\Models\PosExchange;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosExchangeResource extends Resource
{
    protected static ?string $model = PosExchange::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Ventas'); }
    public static function getNavigationLabel(): string { return __('Cambios POS'); }
    public static function getModelLabel(): string { return __('cambio POS'); }
    public static function getPluralModelLabel(): string { return __('cambios POS'); }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAdminPermission('pos_exchanges.view') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()
            ->whereHas('originalOrder', fn (Builder $query) => $query->whereIn('pos_warehouse_id', $warehouseIds));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('originalOrder.number')->label(__('Venta original'))->searchable()->sortable(),
            TextColumn::make('replacementOrder.number')->label(__('Venta de reemplazo'))->searchable(),
            TextColumn::make('originalOrder.customer_name')->label(__('Cliente'))->searchable(),
            TextColumn::make('refund.id')->label(__('Devolución'))->formatStateUsing(fn ($state) => '#'.$state),
            TextColumn::make('returned_amount')->label(__('Valor devuelto'))->formatStateUsing(fn ($state, PosExchange $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('replacement_amount')->label(__('Venta reemplazo'))->formatStateUsing(fn ($state, PosExchange $record) => number_format($state / 100, 2).' '.$record->currency),
            TextColumn::make('difference_amount')->label(__('Diferencia neta'))->formatStateUsing(fn ($state, PosExchange $record) => number_format($state / 100, 2).' '.$record->currency)->color(fn ($state) => $state > 0 ? 'warning' : ($state < 0 ? 'success' : 'gray')),
            TextColumn::make('processor.name')->label(__('Procesado por')),
            TextColumn::make('exchanged_at')->label(__('Fecha'))->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('currency')->options(['CUP' => 'CUP', 'USD' => 'USD', 'EUR' => 'EUR']),
        ])->defaultSort('exchanged_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListPosExchanges::route('/')];
    }
}
