<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
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
    protected static string | UnitEnum | null $navigationGroup = 'Catálogo';
    protected static ?string $navigationLabel = 'Productos';
    protected static ?string $modelLabel = 'producto';
    protected static ?string $pluralModelLabel = 'productos';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Información del producto')->columns(2)->schema([
                TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                TextInput::make('slug')->label('Slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('sku')->label('SKU')->required()->maxLength(100)->unique(ignoreRecord: true),
                Select::make('category_id')->label('Categoría')->relationship('category', 'name')->searchable()->preload(),
                Select::make('brand_id')->label('Marca')->relationship('brand', 'name')->searchable()->preload(),
                TextInput::make('price_amount')->label('Precio (en centavos)')->numeric()->integer()->minValue(0)->required()->helperText('Ejemplo: 1999 representa 19.99 en la moneda seleccionada.'),
                TextInput::make('compare_at_price_amount')->label('Precio anterior (en centavos)')->numeric()->integer()->minValue(0),
                Select::make('currency')->label('Moneda')->options(['USD' => 'USD', 'EUR' => 'EUR', 'CUP' => 'CUP'])->default('USD')->required(),
                Select::make('status')->label('Estado')->options([
                    Product::STATUS_DRAFT => 'Borrador',
                    Product::STATUS_ACTIVE => 'Publicado',
                    Product::STATUS_ARCHIVED => 'Archivado',
                ])->default(Product::STATUS_DRAFT)->required(),
                Toggle::make('is_visible')->label('Visible en la tienda')->default(true),
                Toggle::make('is_featured')->label('Producto destacado')->default(false),
                TextInput::make('meta_title')->label('Título SEO')->maxLength(255),
                Textarea::make('meta_description')->label('Descripción SEO')->rows(2)->columnSpanFull(),
                TextInput::make('short_description')->label('Descripción corta')->maxLength(500)->columnSpanFull(),
                Textarea::make('description')->label('Descripción completa')->rows(7)->columnSpanFull(),
            ]),
            Section::make('Imágenes')->description('La primera imagen marcada como principal se utilizará como imagen destacada del producto.')->schema([
                Repeater::make('images')->relationship()->label('Galería')->schema([
                    FileUpload::make('path')->label('Imagen')->image()->disk('public')->directory('products')->required(),
                    TextInput::make('alt_text')->label('Texto alternativo')->maxLength(255),
                    TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
                    Toggle::make('is_primary')->label('Imagen principal')->default(false),
                ])->columns(2)->orderColumn('sort_order')->collapsible()->defaultItems(0),
            ]),
            Section::make('Variantes')->description('Opciones como talla, color o presentación. El control de existencias se incorporará en la fase de inventario.')->schema([
                Repeater::make('variants')->relationship()->label('Variantes')->schema([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                    TextInput::make('sku')->label('SKU')->required()->maxLength(100),
                    TextInput::make('price_amount')->label('Precio propio (centavos)')->numeric()->integer()->minValue(0),
                    KeyValue::make('options')->label('Opciones')->keyLabel('Atributo')->valueLabel('Valor')->addable()->reorderable(false),
                    Toggle::make('is_active')->label('Activa')->default(true),
                    TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
                ])->columns(2)->reorderable('sort_order')->collapsible()->defaultItems(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Producto')->searchable()->sortable()->limit(35),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('category.name')->label('Categoría')->placeholder('Sin categoría'),
                TextColumn::make('price_amount')->label('Precio')->formatStateUsing(fn ($state, Product $record) => number_format($state / 100, 2).' '.$record->currency)->sortable(),
                TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => match ($state) {
                    Product::STATUS_ACTIVE => 'success',
                    Product::STATUS_ARCHIVED => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Estado')->options([
                    Product::STATUS_DRAFT => 'Borrador',
                    Product::STATUS_ACTIVE => 'Publicado',
                    Product::STATUS_ARCHIVED => 'Archivado',
                ]),
                SelectFilter::make('category')->relationship('category', 'name')->label('Categoría'),
                SelectFilter::make('brand')->relationship('brand', 'name')->label('Marca'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->headerActions([CreateAction::make()->label('Nuevo producto')]);
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
