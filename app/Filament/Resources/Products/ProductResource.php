<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Filament\Support\SlugField;
use App\Filament\Actions\CatalogImportActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Catálogo'); }
    public static function getNavigationLabel(): string { return __('Productos'); }
    public static function getModelLabel(): string { return __('producto'); }
    public static function getPluralModelLabel(): string { return __('productos'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Información del producto'))->columns(2)->schema([
                SlugField::name(label: __('Nombre')),
                SlugField::slug(label: __('Slug')),
                TextInput::make('sku')->label(__('SKU'))->required()->maxLength(100)->unique(ignoreRecord: true),
                Select::make('category_id')->label(__('Categoría'))->relationship('category', 'name')->searchable()->preload(),
                Select::make('brand_id')->label(__('Marca'))->relationship('brand', 'name')->searchable()->preload(),
                TextInput::make('price_amount')->label(__('Precio (en centavos)'))->numeric()->integer()->minValue(0)->required()->helperText(__('Ejemplo: 1999 representa 19.99 en la moneda seleccionada.')),
                TextInput::make('compare_at_price_amount')->label(__('Precio anterior (en centavos)'))->numeric()->integer()->minValue(0),
                Select::make('currency')->label(__('Moneda'))->options(['USD' => 'USD', 'EUR' => 'EUR', 'CUP' => 'CUP'])->default('USD')->required(),
                Select::make('status')->label(__('Estado'))->options([
                    Product::STATUS_DRAFT => __('Borrador'),
                    Product::STATUS_ACTIVE => __('Publicado'),
                    Product::STATUS_ARCHIVED => __('Archivado'),
                ])->default(Product::STATUS_DRAFT)->required(),
                Toggle::make('is_visible')->label(__('Visible en la tienda'))->default(true),
                Toggle::make('is_featured')->label(__('Producto destacado'))->default(false),
                TextInput::make('meta_title')->label(__('Título SEO'))->maxLength(255),
                Textarea::make('meta_description')->label(__('Descripción SEO'))->rows(2)->columnSpanFull(),
                TextInput::make('short_description')->label(__('Descripción corta'))->maxLength(500)->columnSpanFull(),
                Textarea::make('description')->label(__('Descripción completa'))->rows(7)->columnSpanFull(),
            ]),
            Section::make(__('Imágenes'))->description(__('La primera imagen marcada como principal se utilizará como imagen destacada del producto.'))->schema([
                Repeater::make('images')->relationship()->label(__('Galería'))->schema([
                    FileUpload::make('path')->label(__('Imagen'))->image()->maxSize(5120)->disk('public')->directory('products')->required(),
                    TextInput::make('alt_text')->label(__('Texto alternativo'))->maxLength(255),
                    TextInput::make('sort_order')->label(__('Orden'))->numeric()->integer()->default(0),
                    Toggle::make('is_primary')->label(__('Imagen principal'))->default(false),
                ])->columns(2)->orderColumn('sort_order')->collapsible()->defaultItems(0),
            ]),
            Section::make(__('Variantes'))->description(__('Opciones como talla, color o presentación. El control de existencias se incorporará en la fase de inventario.'))->schema([
                Repeater::make('variants')->relationship()->label(__('Variantes'))->schema([
                    TextInput::make('name')->label(__('Nombre'))->required()->maxLength(255),
                    TextInput::make('sku')->label(__('SKU'))->required()->maxLength(100),
                    TextInput::make('price_amount')->label(__('Precio propio (centavos)'))->numeric()->integer()->minValue(0),
                    KeyValue::make('options')->label(__('Opciones'))->keyLabel(__('Atributo'))->valueLabel(__('Valor'))->addable()->reorderable(false),
                    Toggle::make('is_active')->label(__('Activa'))->default(true),
                    TextInput::make('sort_order')->label(__('Orden'))->numeric()->integer()->default(0),
                ])->columns(2)->reorderable('sort_order')->collapsible()->defaultItems(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Producto'))->searchable()->sortable()->limit(35),
                TextColumn::make('sku')->label(__('SKU'))->searchable(),
                TextColumn::make('category.name')->label(__('Categoría'))->placeholder(__('Sin categoría')),
                TextColumn::make('price_amount')->label(__('Precio'))->formatStateUsing(fn ($state, Product $record) => number_format($state / 100, 2).' '.$record->currency)->sortable(),
                TextColumn::make('status')->label(__('Estado'))->badge()->color(fn (string $state): string => match ($state) {
                    Product::STATUS_ACTIVE => 'success',
                    Product::STATUS_ARCHIVED => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('updated_at')->label(__('Actualizado'))->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('Estado'))->options([
                    Product::STATUS_DRAFT => __('Borrador'),
                    Product::STATUS_ACTIVE => __('Publicado'),
                    Product::STATUS_ARCHIVED => __('Archivado'),
                ]),
                SelectFilter::make('category')->relationship('category', 'name')->label(__('Categoría')),
                SelectFilter::make('brand')->relationship('brand', 'name')->label(__('Marca')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->headerActions([...CatalogImportActions::make('products'), CreateAction::make()->label(__('Nuevo producto'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
