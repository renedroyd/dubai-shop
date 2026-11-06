<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_authenticated_users_cannot_access_filament_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_migrations_create_default_roles_and_superadmin_role_is_protected(): void
    {
        $this->assertDatabaseHas('admin_roles', ['slug' => 'superadmin', 'is_system' => true]);
        $this->assertDatabaseHas('admin_roles', ['slug' => 'catalog-manager', 'is_system' => true]);
    }

    public function test_superadministrator_can_access_filament_dashboard(): void
    {
        $this->withoutVite();

        $user = User::factory()->create(['is_admin' => true, 'admin_access_enabled' => true]);
        $role = AdminRole::query()->where('slug', 'superadmin')->firstOrFail();
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_permissions_are_checked_per_resource_and_action(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Solo lectura de productos',
            'slug' => 'products-reader',
            'permissions' => ['panel.access', 'products.view'],
        ]);
        $user->roles()->attach($role);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Product::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', Product::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', Product::class));
    }



    public function test_panel_user_without_product_permission_cannot_open_product_resource(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Solo panel',
            'slug' => 'panel-only',
            'permissions' => ['panel.access'],
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/admin/products')->assertForbidden();
    }

    public function test_product_read_permission_does_not_grant_create_access(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Consulta de productos',
            'slug' => 'product-read-only',
            'permissions' => ['panel.access', 'products.view'],
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/admin/products')->assertOk();
        $this->actingAs($user)->get('/admin/products/create')->assertForbidden();
    }

    public function test_superadministrator_cannot_modify_the_emergency_role_or_account(): void
    {
        $admin = User::factory()->create(['admin_access_enabled' => true]);
        $superadminRole = AdminRole::query()->where('slug', 'superadmin')->firstOrFail();
        $admin->roles()->attach($superadminRole);

        $this->assertFalse(Gate::forUser($admin)->allows('update', $superadminRole));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_user_without_panel_access_permission_is_denied(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $role = AdminRole::query()->create([
            'name' => 'Sin acceso',
            'slug' => 'no-panel-access',
            'permissions' => ['products.view'],
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_access_disabled_user_is_denied_even_with_superadmin_role(): void
    {
        $user = User::factory()->create(['admin_access_enabled' => false]);
        $user->roles()->attach(AdminRole::query()->where('slug', 'superadmin')->firstOrFail());

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
