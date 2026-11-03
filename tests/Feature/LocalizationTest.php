<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_storefront_language_can_be_changed_and_persisted_for_a_guest(): void
    {
        $this->post(route('locale.update', 'en'), ['scope' => 'storefront'])
            ->assertRedirect(route('home'))
            ->assertCookie('storefront_locale', 'en');

        $this->withCookie('storefront_locale', 'en')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Skip to content');
    }

    public function test_customer_storefront_and_admin_languages_are_saved_independently(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->post(route('locale.update', 'en'), ['scope' => 'storefront'])->assertRedirect();
        $this->actingAs($user)->post(route('locale.update', 'es'), ['scope' => 'admin'])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'storefront_locale' => 'en',
            'admin_locale' => 'es',
        ]);
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->post(route('locale.update', 'fr'), ['scope' => 'storefront'])->assertNotFound();
    }

    public function test_invalid_language_scope_is_rejected(): void
    {
        $this->post(route('locale.update', 'en'), ['scope' => 'unknown'])->assertSessionHasErrors('scope');
    }
}
