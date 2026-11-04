<?php

namespace Tests\Feature;

use App\Models\StorefrontBanner;
use App\Models\StorefrontMenuItem;
use App\Models\StorefrontPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_home_banner_within_its_schedule_is_displayed(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        Storage::disk('public')->put('storefront/banners/sale.jpg', 'image');

        StorefrontBanner::query()->create([
            'title' => 'Campaña de temporada',
            'eyebrow' => 'EDICIÓN ESPECIAL',
            'description' => 'Descubre la selección.',
            'image_path' => 'storefront/banners/sale.jpg',
            'image_alt' => 'Colección de temporada',
            'link_url' => '/productos',
            'link_label' => 'Comprar ahora',
            'placement' => 'home',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('campaign-banner')
            ->assertSee('campaign-banner__image')
            ->assertSee('campaign-banner__overlay')
            ->assertSee('campaign-banner__content')
            ->assertSee('Campaña de temporada')
            ->assertSee('EDICIÓN ESPECIAL')
            ->assertSee('Comprar ahora');
    }

    public function test_inactive_or_expired_banners_are_not_displayed(): void
    {
        $this->withoutVite();

        StorefrontBanner::query()->create([
            'title' => 'Campaña vencida',
            'image_path' => 'expired.jpg',
            'placement' => 'home',
            'ends_at' => now()->subMinute(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        StorefrontBanner::query()->create([
            'title' => 'Campaña desactivada',
            'image_path' => 'disabled.jpg',
            'placement' => 'home',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Campaña vencida')
            ->assertDontSee('Campaña desactivada');
    }

    public function test_only_published_pages_are_publicly_accessible_and_content_is_escaped(): void
    {
        $this->withoutVite();

        StorefrontPage::query()->create([
            'title' => 'Política de privacidad',
            'slug' => 'privacidad',
            'content' => '<script>alert("x")</script>\nProtegemos tus datos.',
            'status' => 'published',
        ]);

        StorefrontPage::query()->create([
            'title' => 'Borrador interno',
            'slug' => 'borrador-interno',
            'content' => 'No publicar.',
            'status' => 'draft',
        ]);

        $this->get(route('pages.show', 'privacidad'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('Protegemos tus datos.')
            ->assertDontSee('<script>alert("x")</script>', false);

        $this->get(route('pages.show', 'borrador-interno'))->assertNotFound();
        $this->get(route('pages.show', 'no-existe'))->assertNotFound();
    }

    public function test_configured_header_menu_replaces_default_navigation(): void
    {
        $this->withoutVite();

        StorefrontMenuItem::query()->create([
            'label' => 'Preguntas frecuentes',
            'url' => '/paginas/preguntas-frecuentes',
            'location' => 'header',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Preguntas frecuentes')
            ->assertDontSee('Nuestra esencia');
    }
}
