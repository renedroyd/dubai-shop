<?php

namespace App\Filament\Resources\StorefrontPages;

use App\Models\StorefrontPage;
use App\Filament\Support\SlugField;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use UnitEnum;

class StorefrontPageResource extends Resource
{
    protected static ?string $model = StorefrontPage::class;
    public static function getNavigationGroup(): string|\UnitEnum|null { return __('Tienda'); }
    public static function getNavigationLabel(): string { return __('Páginas'); }
    public static function getModelLabel(): string { return __('página'); }
    public static function getPluralModelLabel(): string { return __('páginas'); }
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Página'))->columns(2)->schema([
                SlugField::name(field: 'title', slugField: 'slug', label: __('Título')), 
                SlugField::slug(label: __('URL (slug)')), 
                Textarea::make('content')->label(__('Contenido'))->rows(16)->required()->columnSpanFull()
                    ->helperText(__('Se muestra como texto plano con saltos de línea; no se permite HTML arbitrario.')),
            ]),
            Section::make(__('Publicación y SEO'))->columns(2)->schema([
                Select::make('status')->label(__('Estado'))->options(['draft' => __('Borrador'), 'published' => __('Publicada')])->required()->default('draft'),
                DateTimePicker::make('published_at')->label(__('Publicar desde')),
                TextInput::make('meta_title')->label(__('Título SEO'))->maxLength(255),
                Textarea::make('meta_description')->label(__('Descripción SEO'))->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label(__('Página'))->searchable()->sortable(),
            TextColumn::make('slug')->label(__('URL'))->copyable(),
            TextColumn::make('status')->label(__('Estado'))->badge()->color(fn (string $state): string => $state === 'published' ? 'success' : 'warning'),
            TextColumn::make('published_at')->label(__('Publicación'))->dateTime(),
        ])->filters([SelectFilter::make('status')->options(['draft' => __('Borrador'), 'published' => __('Publicada')])])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label(__('Nueva página'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStorefrontPages::route('/'),
            'create' => Pages\CreateStorefrontPage::route('/create'),
            'edit' => Pages\EditStorefrontPage::route('/{record}/edit'),
        ];
    }
}
