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
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Tienda'); }
    public static function getNavigationLabel(): string { return __('Menús'); }
    protected static ?string $modelLabel = 'enlace de menú';
    protected static ?string $pluralModelLabel = 'menús';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label(__('Texto'))->required()->maxLength(120),
            TextInput::make('url')->label(__('URL o ruta'))->required()->maxLength(2048)
                ->rules(['regex:/^(\/|https?:\/\/|#)/i'])
                ->helperText(__('Usa una ruta interna que comience por /, un enlace https:// o un ancla #.')),
            Select::make('location')->label(__('Ubicación'))->options(['header' => 'Cabecera', 'footer' => 'Pie de página'])->required()->default('header'),
            Select::make('parent_id')->label(__('Elemento padre'))->options(fn (?StorefrontMenuItem $record) => StorefrontMenuItem::query()->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->orderBy('sort_order')->pluck('label', 'id'))->searchable()->placeholder(__('Sin elemento padre')),
            Toggle::make('open_in_new_tab')->label(__('Abrir en pestaña nueva'))->default(false),
            Toggle::make('is_active')->label(__('Activo'))->default(true),
            TextInput::make('sort_order')->label(__('Orden'))->numeric()->integer()->default(0)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label')->label(__('Enlace'))->searchable()->sortable(),
            TextColumn::make('location')->label(__('Ubicación'))->badge(),
            TextColumn::make('parent.label')->label(__('Padre'))->placeholder(__('Principal')),
            IconColumn::make('is_active')->label(__('Activo'))->boolean(),
            TextColumn::make('sort_order')->label(__('Orden'))->sortable(),
        ])->filters([SelectFilter::make('location')->options(['header' => 'Cabecera', 'footer' => 'Pie'])])
            ->defaultSort('sort_order')->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label(__('Nuevo enlace'))]);
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
