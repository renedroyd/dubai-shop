<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

final class SlugField
{
    public static function name(
        string $label = 'Nombre',
        string $field = 'name',
        string $slugField = 'slug',
    ): TextInput {
        return TextInput::make($field)
            ->label($label)
            ->required()
            ->maxLength(255)
            ->live(debounce: 500)
            ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set) use ($slugField): void {
                if (self::shouldSync($get($slugField), $old)) {
                    $set($slugField, self::from($state));
                }
            });
    }

    public static function slug(string $label = 'Slug', string $field = 'slug'): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->live(onBlur: true)
            ->afterStateUpdated(fn (?string $state, Set $set) => $set($field, self::from($state)))
            ->dehydrateStateUsing(fn (?string $state): string => self::from($state))
            ->helperText(__('Se genera automáticamente desde el nombre. Puedes personalizarlo; se normalizará al guardar.'));
    }

    public static function from(?string $value): string
    {
        return Str::slug($value ?? '');
    }

    public static function shouldSync(?string $currentSlug, ?string $previousName): bool
    {
        return trim($currentSlug ?? '') === '' || $currentSlug === self::from($previousName);
    }
}
