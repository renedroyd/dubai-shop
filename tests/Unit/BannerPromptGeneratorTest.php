<?php

namespace Tests\\Unit;

use App\\Support\\Storefront\\BannerPromptGenerator;
use Tests\\TestCase;

class BannerPromptGeneratorTest extends TestCase
{
    public function test_it_builds_a_premium_prompt_for_a_storefront_banner(): void
    {
        $prompt = app(BannerPromptGenerator::class)->generate([
            'campaign_type' => 'combos',
            'visual_style' => 'cinematic',
            'format' => 'wide',
            'brief' => 'Combo familiar de productos seleccionados.',
        ]);

        $this->assertStringContainsString('Clásico Dubai', $prompt);
        $this->assertStringContainsString('negro obsidiana', $prompt);
        $this->assertStringContainsString('combos y paquetes de productos', $prompt);
        $this->assertStringContainsString('panorámico 2.4:1', $prompt);
        $this->assertStringContainsString('Combo familiar de productos seleccionados.', $prompt);
        $this->assertStringContainsString('No incluyas palabras', $prompt);
    }

    public function test_it_uses_safe_defaults_for_unknown_options(): void
    {
        $prompt = app(BannerPromptGenerator::class)->generate([
            'campaign_type' => 'unknown',
            'visual_style' => 'unknown',
            'format' => 'unknown',
            'brief' => 'Banner de prueba.',
        ]);

        $this->assertStringContainsString('promoción comercial', $prompt);
        $this->assertStringContainsString('editorial de lujo', $prompt);
        $this->assertStringContainsString('panorámico 2.4:1', $prompt);
    }
}
