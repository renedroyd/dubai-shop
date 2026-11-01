<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StorefrontSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_content_managed_from_database(): void
    {
        $this->withoutVite();

        StorefrontSetting::query()->where('key', 'home.hero_title_line_1')->firstOrFail()
            ->update(['value' => 'Una tienda diferente.']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Una tienda diferente.')
            ->assertSee('Una selección especial. Una experiencia diferente.');
    }

    public function test_homepage_lists_active_categories_as_storefront_collections(): void
    {
        $this->withoutVite();

        Category::query()->create([
            'name' => 'Tecnología',
            'slug' => 'tecnologia',
            'description' => 'Dispositivos y accesorios.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Tecnología')
            ->assertSee('Dispositivos y accesorios.');
    }

    public function test_non_public_settings_are_not_rendered_on_the_storefront(): void
    {
        $this->withoutVite();

        StorefrontSetting::query()->where('key', 'home.hero_title_line_1')->firstOrFail()
            ->update(['value' => 'Texto privado', 'is_public' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Texto privado');
    }
}
