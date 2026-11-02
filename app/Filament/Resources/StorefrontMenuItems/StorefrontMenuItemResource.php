<?php

namespace App\Filament\Resources\StorefrontMenuItems;

use App\Models\StorefrontMenuItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use UnitEnum;

class StorefrontMenuItemResource extends Resource
{
    protected static ?string $model = StorefrontMenuItem::class;
    protected static string | UnitEnum | null $navigationGroup = 'Tienda';
    protected static ?string $navigationLabel = 'Menús';
    protected static ?string $modelLabel = 'enlace de menú';
    protected static ?string $pluralModelLabel = 'menús';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label('Texto')->required()->maxLength(120),
            TextInput::make('url')->label('URL o ruta')->required()->maxLength(2048)
                ->helperText('Ejemplos: /productos, /paginas/privacidad o https://example.com'),
            Select::make('location')->label('Ubicación')->options(['header' => 'Cabecera', 'footer' => 'Pie de página'])->required()->default('header'),
            Select::make('parent_id')->label('Elemento padre')->options(fn (?StorefrontMenuItem $record) => StorefrontMenuItem::query()->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->orderBy('sort_order')->pluck('label', 'id'))->searchable()->placeholder('Sin elemento padre'),
            Toggle::make('open_in_new_tab')->label('Abrir en pestaña nueva')->default(false),
            Toggle::make('is_active')->label('Activo')->default(true),
            TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label')->label('Enlace')->searchable()->sortable(),
            TextColumn::make('location')->label('Ubicación')->badge(),
            TextColumn::make('parent.label')->label('Padre')->placeholder('Principal'),
            IconColumn::make('is_active')->label('Activo')->boolean(),
            TextColumn::make('sort_order')->label('Orden')->sortable(),
        ])->filters([SelectFilter::make('location')->options(['header' => 'Cabecera', 'footer' => 'Pie'])])
            ->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label('Nuevo enlace')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStorefrontMenuItems::route('/'),
            'create' => Pages\CreateStorefrontMenuItem::route('/create'),
            'edit' => Pages\EditStorefrontMenuItem::route('/{record}/edit'),
        ];
    }
}
