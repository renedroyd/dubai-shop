<?php

namespace App\Filament\Resources\PosTerminals;

use App\Filament\Resources\PosTerminals\Pages\CreatePosTerminal;
use App\Filament\Resources\PosTerminals\Pages\EditPosTerminal;
use App\Filament\Resources\PosTerminals\Pages\ListPosTerminals;
use App\Models\PosTerminal;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PosTerminalResource extends Resource
{
    protected static ?string $model = PosTerminal::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Punto de venta'); }
    public static function getNavigationLabel(): string { return __('Terminales'); }
    public static function getModelLabel(): string { return __('terminal'); }
    public static function getPluralModelLabel(): string { return __('terminales'); }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $warehouseIds = auth()->user()?->warehouseIdsWithPermission('view') ?? [];

        return parent::getEloquentQuery()->whereIn('warehouse_id', $warehouseIds);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Configuración del terminal'))->schema([
                Select::make('pos_location_id')
                    ->label(__('Establecimiento'))
                    ->relationship('location', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))
                    ->searchable()->preload()->required(),
                Select::make('warehouse_id')
                    ->label(__('Almacén asignado'))
                    ->relationship('warehouse', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))
                    ->searchable()->preload()->required()
                    ->helperText(__('Las ventas de este terminal descontarán existencias de este almacén.')),
                TextInput::make('code')->label(__('Código'))->required()->alphaDash()->maxLength(40),
                TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
                Select::make('receipt_width')->label(__('Ancho del recibo'))->options(['58' => '58 mm', '80' => '80 mm'])->default('80')->required(),
                TextInput::make('receipt_copies')->label(__('Copias del recibo'))->numeric()->integer()->minValue(1)->maxValue(3)->default(1)->required(),
                TextInput::make('printer_name')->label(__('Impresora QZ Tray'))->maxLength(255)
                    ->helperText(__('Nombre exacto de la impresora instalada en la computadora de caja.')),
                Toggle::make('cash_drawer_enabled')->label(__('Gaveta de efectivo habilitada'))->default(false),
                Select::make('cash_drawer_pin')->label(__('Pin de apertura ESC/POS'))->options([0 => 'Pin 2', 1 => 'Pin 5'])->default(0)->required()
                    ->visible(fn ($get): bool => (bool) $get('cash_drawer_enabled')),
                Textarea::make('notes')->label(__('Notas internas'))->rows(3),
                Toggle::make('is_active')->label(__('Activo'))->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('Código'))->searchable()->sortable(),
            TextColumn::make('name')->label(__('Terminal'))->searchable()->sortable(),
            TextColumn::make('location.name')->label(__('Establecimiento'))->sortable(),
            TextColumn::make('warehouse.name')->label(__('Almacén asignado'))->sortable(),
            IconColumn::make('is_active')->label(__('Activo'))->boolean(),
        ])->filters([
            TernaryFilter::make('is_active')->label(__('Estado')),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->requiresConfirmation()
                ->visible(fn (PosTerminal $record): bool => ! $record->cashSessions()->exists() && ! $record->orders()->exists()),
        ])->headerActions([
            CreateAction::make()->label(__('Nuevo terminal')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosTerminals::route('/'),
            'create' => CreatePosTerminal::route('/create'),
            'edit' => EditPosTerminal::route('/{record}/edit'),
        ];
    }
}
