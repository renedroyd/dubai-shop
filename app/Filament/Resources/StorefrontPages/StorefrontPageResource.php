<?php

namespace App\Filament\Resources\StorefrontPages;

use App\Models\StorefrontPage;
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
    protected static string | UnitEnum | null $navigationGroup = 'Tienda';
    protected static ?string $navigationLabel = 'Páginas';
    protected static ?string $modelLabel = 'página';
    protected static ?string $pluralModelLabel = 'páginas';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Página')->columns(2)->schema([
                TextInput::make('title')->label('Título')->required()->maxLength(255),
                TextInput::make('slug')->label('URL (slug)')->required()->unique(ignoreRecord: true)->alphaDash()->maxLength(255),
                Textarea::make('content')->label('Contenido')->rows(16)->required()->columnSpanFull()
                    ->helperText('Se muestra como texto plano con saltos de línea; no se permite HTML arbitrario.'),
            ]),
            Section::make('Publicación y SEO')->columns(2)->schema([
                Select::make('status')->label('Estado')->options(['draft' => 'Borrador', 'published' => 'Publicada'])->required()->default('draft'),
                DateTimePicker::make('published_at')->label('Publicar desde'),
                TextInput::make('meta_title')->label('Título SEO')->maxLength(255),
                Textarea::make('meta_description')->label('Descripción SEO')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Página')->searchable()->sortable(),
            TextColumn::make('slug')->label('URL')->copyable(),
            TextColumn::make('status')->label('Estado')->badge()->color(fn (string $state): string => $state === 'published' ? 'success' : 'warning'),
            TextColumn::make('published_at')->label('Publicación')->dateTime(),
        ])->filters([SelectFilter::make('status')->options(['draft' => 'Borrador', 'published' => 'Publicada'])])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([CreateAction::make()->label('Nueva página')]);
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
