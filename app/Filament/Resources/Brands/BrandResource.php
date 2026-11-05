<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Models\Brand;
use App\Filament\Support\SlugField;
use App\Filament\Actions\CatalogImportActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use UnitEnum;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Catálogo'); }
    public static function getNavigationLabel(): string { return __('Marcas'); }
    public static function getModelLabel(): string { return __('marca'); }
    public static function getPluralModelLabel(): string { return __('marcas'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SlugField::name(label: __('Nombre')),
            SlugField::slug(label: __('Slug')),
            Textarea::make('description')->label(__('Descripción'))->rows(3),
            FileUpload::make('logo_path')->label(__('Logo'))->image()->disk('public')->directory('brands'),
            Toggle::make('is_active')->label(__('Activa'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('Marca'))->searchable()->sortable(),
            TextColumn::make('products_count')->counts('products')->label(__('Productos')),
            IconColumn::make('is_active')->label(__('Activa'))->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
          ->headerActions([...CatalogImportActions::make('brands'), CreateAction::make()->label(__('Nueva marca'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }
}
