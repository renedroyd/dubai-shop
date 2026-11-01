<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static string | UnitEnum | null $navigationGroup = 'Catálogo';
    protected static ?string $navigationLabel = 'Categorías';
    protected static ?string $modelLabel = 'categoría';
    protected static ?string $pluralModelLabel = 'categorías';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('slug')->label('Slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('parent_id')->label('Categoría superior')->relationship('parent', 'name')->searchable()->preload()->nullable(),
            Textarea::make('description')->label('Descripción')->rows(3),
            FileUpload::make('image_path')->label('Imagen')->image()->disk('public')->directory('categories'),
            TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
            Toggle::make('is_active')->label('Activa')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Categoría')->searchable()->sortable(),
            TextColumn::make('parent.name')->label('Superior')->placeholder('Principal'),
            TextColumn::make('products_count')->counts('products')->label('Productos'),
            IconColumn::make('is_active')->label('Activa')->boolean(),
            TextColumn::make('sort_order')->label('Orden')->sortable(),
        ])->defaultSort('sort_order')
          ->recordActions([EditAction::make(), DeleteAction::make()])
          ->headerActions([CreateAction::make()->label('Nueva categoría')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
