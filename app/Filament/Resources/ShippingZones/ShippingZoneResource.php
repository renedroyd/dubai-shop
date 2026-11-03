<?php

namespace App\Filament\Resources\ShippingZones;

use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZones\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Models\ShippingZone;
use App\Services\Shipping\ShippingRateService;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Configuración'); }
    public static function getNavigationLabel(): string { return __('Zonas y tarifas de envío'); }
    public static function getModelLabel(): string { return __('tarifa de envío'); }
    public static function getPluralModelLabel(): string { return __('zonas y tarifas de envío'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('province')->label(__('Provincia'))->options(ShippingRateService::provinces())->searchable()->required(),
            TextInput::make('municipality')->label(__('Municipio'))->maxLength(120)->rules(['required_with:locality'])
                ->helperText(__('Déjalo vacío para aplicar la tarifa a toda la provincia.')),
            TextInput::make('locality')->label(__('Localidad / reparto'))->maxLength(160)
                ->helperText(__('Opcional. Si se completa, esta tarifa tiene prioridad sobre municipio y provincia.')),
            TextInput::make('shipping_amount')->label(__('Costo de envío'))->numeric()->minValue(0)->step('0.01')->required(),
            Select::make('currency')->label(__('Moneda'))->options([
                'USD' => __('USD — Dólar estadounidense'),
                'CUP' => __('CUP — Peso cubano'),
                'EUR' => __('EUR — Euro'),
            ])->default('USD')->required(),
            Toggle::make('is_active')->label(__('Tarifa activa'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('province')->label(__('Provincia'))->searchable()->sortable(),
            TextColumn::make('municipality')->label(__('Municipio'))->placeholder(__('Toda la provincia'))->searchable(),
            TextColumn::make('locality')->label(__('Localidad'))->placeholder(__('Todas'))->searchable(),
            TextColumn::make('shipping_amount')->label(__('Costo'))->formatStateUsing(fn ($state, ShippingZone $record) => number_format((float) $state, 2).' '.$record->currency)->sortable(),
            IconColumn::make('is_active')->label(__('Activa'))->boolean(),
        ])->filters([
            SelectFilter::make('province')->options(ShippingRateService::provinces()),
            SelectFilter::make('is_active')->options([1 => 'Activas', 0 => 'Inactivas']),
        ])->defaultSort('province')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label(__('Nueva tarifa'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
