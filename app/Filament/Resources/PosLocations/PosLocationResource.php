<?php

namespace App\Filament\Resources\PosLocations;

use App\Filament\Resources\PosLocations\Pages\CreatePosLocation;
use App\Filament\Resources\PosLocations\Pages\EditPosLocation;
use App\Filament\Resources\PosLocations\Pages\ListPosLocations;
use App\Models\PosLocation;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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

class PosLocationResource extends Resource
{
    protected static ?string $model = PosLocation::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string | \UnitEnum | null { return __('Punto de venta'); }
    public static function getNavigationLabel(): string { return __('Establecimientos'); }
    public static function getModelLabel(): string { return __('establecimiento'); }
    public static function getPluralModelLabel(): string { return __('establecimientos'); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Datos del establecimiento'))->schema([
                TextInput::make('code')->label(__('Código'))->required()->alphaDash()->maxLength(40)->unique(ignoreRecord: true),
                TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
                TextInput::make('phone')->label(__('Teléfono'))->tel()->maxLength(40),
                Textarea::make('address')->label(__('Dirección'))->rows(2),
                Textarea::make('notes')->label(__('Notas internas'))->rows(3),
                Toggle::make('is_active')->label(__('Activo'))->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('Código'))->searchable()->sortable(),
            TextColumn::make('name')->label(__('Establecimiento'))->searchable()->sortable(),
            TextColumn::make('phone')->label(__('Teléfono')),
            TextColumn::make('terminals_count')->counts('terminals')->label(__('Terminales')),
            IconColumn::make('is_active')->label(__('Activo'))->boolean(),
        ])->filters([
            TernaryFilter::make('is_active')->label(__('Estado')),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make()->requiresConfirmation()
                ->visible(fn (PosLocation $record): bool => ! $record->terminals()->exists()),
        ])->headerActions([
            CreateAction::make()->label(__('Nuevo establecimiento')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosLocations::route('/'),
            'create' => CreatePosLocation::route('/create'),
            'edit' => EditPosLocation::route('/{record}/edit'),
        ];
    }
}
