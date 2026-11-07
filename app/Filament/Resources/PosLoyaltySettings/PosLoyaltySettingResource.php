<?php

namespace App\Filament\Resources\PosLoyaltySettings;

use App\Filament\Resources\PosLoyaltySettings\Pages\EditPosLoyaltySetting;
use App\Filament\Resources\PosLoyaltySettings\Pages\ListPosLoyaltySettings;
use App\Models\PosLoyaltySetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosLoyaltySettingResource extends Resource
{
    protected static ?string $model = PosLoyaltySetting::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';
    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Punto de venta'); }
    public static function getNavigationLabel(): string { return __('Fidelización'); }
    public static function getModelLabel(): string { return __('configuración de fidelización'); }
    public static function getPluralModelLabel(): string { return __('configuración de fidelización'); }

    public static function canViewAny(): bool { return auth()->user()?->hasAdminPermission('pos_loyalty_settings.view') ?? false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return auth()->user()?->hasAdminPermission('pos_loyalty_settings.update') ?? false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Reglas de fidelización por moneda'))->columns(2)->schema([
                TextInput::make('currency')->label(__('Moneda'))->disabled()->dehydrated(false),
                Toggle::make('is_enabled')->label(__('Programa activo para esta moneda')),
                TextInput::make('earning_amount')->label(__('Importe elegible para acumular'))->numeric()->integer()->minValue(1)->required()
                    ->helperText(fn (PosLoyaltySetting $record): string => $record->currency === 'CUP'
                        ? __('Un punto por cada :amount CUP. Introduce el importe en unidades menores (100 CUP = 10 000).', ['amount' => number_format($record->earning_amount / 100, 2)])
                        : __('Un punto por cada :amount :currency. Introduce el importe en unidades menores (1 :currency = 100).', ['amount' => number_format($record->earning_amount / 100, 2), 'currency' => $record->currency])),
                TextInput::make('points_per_earning_unit')->label(__('Puntos por importe elegible'))->numeric()->integer()->minValue(1)->required(),
                TextInput::make('redemption_amount')->label(__('Valor monetario de cada punto (unidades menores)'))->numeric()->integer()->minValue(1)->required()
                    ->helperText(fn (PosLoyaltySetting $record): string => __('Valor actual: :amount :currency por punto.', ['amount' => number_format($record->redemption_amount / 100, 2), 'currency' => $record->currency])),
                TextInput::make('minimum_redemption_points')->label(__('Canje mínimo (puntos)'))->numeric()->integer()->minValue(1)->required(),
                TextInput::make('maximum_redemption_basis_points')->label(__('Descuento máximo (puntos básicos)'))->numeric()->integer()->minValue(1)->maxValue(10000)->required()
                    ->helperText(__('2000 = 20 %. 10000 = 100 %.')),
                TextInput::make('expiration_months')->label(__('Vencimiento (meses)'))->numeric()->integer()->minValue(1)->maxValue(120)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('currency')->label(__('Moneda'))->badge(),
            TextColumn::make('earning_amount')->label(__('Acumulación'))->state(fn (PosLoyaltySetting $record): string => number_format($record->earning_amount / 100, 2).' '.$record->currency),
            TextColumn::make('points_per_earning_unit')->label(__('Puntos por tramo')),
            TextColumn::make('redemption_amount')->label(__('Valor por punto'))->state(fn (PosLoyaltySetting $record): string => number_format($record->redemption_amount / 100, 2).' '.$record->currency),
            TextColumn::make('minimum_redemption_points')->label(__('Canje mínimo')),
            TextColumn::make('maximum_redemption_basis_points')->label(__('Límite'))->state(fn (PosLoyaltySetting $record): string => number_format($record->maximum_redemption_basis_points / 100, 2).'%'),
            TextColumn::make('expiration_months')->label(__('Vencimiento'))->suffix(' meses'),
            IconColumn::make('is_enabled')->label(__('Activo'))->boolean(),
        ])->recordActions([
            \Filament\Actions\EditAction::make()->visible(fn (PosLoyaltySetting $record): bool => static::canEdit($record)),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosLoyaltySettings::route('/'),
            'edit' => EditPosLoyaltySetting::route('/{record}/edit'),
        ];
    }
}
