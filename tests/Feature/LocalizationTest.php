<?php

namespace Tests\Feature;

use App\Models\AdminRole;
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

    public function test_spanish_is_the_default_storefront_language(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('Ingresar');
    }

    public function test_admin_locale_is_applied_to_the_filament_panel(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'admin_access_enabled' => true,
            'admin_locale' => 'en',
        ]);
        $admin->roles()->attach(AdminRole::query()->where('slug', 'superadmin')->firstOrFail());

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('admin-language')
            ->assertSee('English');

        $this->assertSame('en', app()->getLocale());
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
