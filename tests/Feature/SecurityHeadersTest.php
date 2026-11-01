<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_responses_include_baseline_security_headers(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_cart_page_is_marked_noindex(): void
    {
        $this->withoutVite();

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_robots_route_hides_admin_and_declares_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee(route('sitemap'));
    }
}
