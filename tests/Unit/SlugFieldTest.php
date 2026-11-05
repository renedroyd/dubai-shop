<?php

namespace Tests\\Unit;

use App\\Filament\\Support\\SlugField;
use PHPUnit\\Framework\\Attributes\\DataProvider;
use PHPUnit\\Framework\\TestCase;

class SlugFieldTest extends TestCase
{
    #[DataProvider('slugCases')]
    public function test_it_generates_url_friendly_slugs(?string $value, string $expected): void
    {
        $this->assertSame($expected, SlugField::from($value));
    }

    public static function slugCases(): array
    {
        return [
            'spanish accents and punctuation' => ['Café Dubai: ¡Ofertas y Más!', 'cafe-dubai-ofertas-y-mas'],
            'multiple spaces' => ['  Combos   especiales  ', 'combos-especiales'],
            'empty value' => [null, ''],
        ];
    }

    public function test_it_only_syncs_when_slug_is_empty_or_still_matches_previous_name(): void
    {
        $this->assertTrue(SlugField::shouldSync(null, 'Café Dubai'));
        $this->assertTrue(SlugField::shouldSync('', 'Café Dubai'));
        $this->assertTrue(SlugField::shouldSync('cafe-dubai', 'Café Dubai'));
        $this->assertFalse(SlugField::shouldSync('mi-url-personalizada', 'Café Dubai'));
    }
}
