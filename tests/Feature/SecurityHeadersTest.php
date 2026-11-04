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
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_only_for_secure_requests(): void
    {
        $this->get('https://localhost/robots.txt')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_untrusted_forwarded_proto_does_not_enable_hsts(): void
    {
        $this->withServerVariables(['HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get(route('robots'))
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_configured_reverse_proxy_can_assert_https(): void
    {
        config(['app.trusted_proxies' => ['127.0.0.1']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get(route('robots'))
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
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

    public function test_storefront_mutations_have_rate_limits(): void
    {
        $routes = app('router')->getRoutes();

        foreach (['cart.items.store', 'cart.items.update', 'cart.items.destroy'] as $name) {
            $this->assertContains('throttle:30,1', $routes->getByName($name)->gatherMiddleware());
        }

        $this->assertContains('throttle:10,1', $routes->getByName('locale.update')->gatherMiddleware());
    }
}
