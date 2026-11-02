<?php

namespace Tests\Feature\Storefront;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_reach_account_dashboard(): void
    {
        $response = $this->post(route('customer.register.store'), [
            'name' => 'Cliente Dubai',
            'email' => 'CLIENTE@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'cliente@example.com',
            'is_admin' => false,
        ]);
        $this->get(route('customer.dashboard'))->assertOk();
    }

    public function test_customer_can_log_in_and_log_out(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->post(route('customer.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('customer.logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_customer_can_save_address_and_other_customers_cannot_delete_it(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);

        $this->actingAs($owner)->post(route('customer.addresses.store'), [
            'label' => 'Casa',
            'recipient_name' => 'Cliente Dubai',
            'phone' => '53000000',
            'address' => 'Calle 1',
            'city' => 'Guantánamo',
            'region' => 'guantanamo',
            'country' => 'CU',
            'is_default' => '1',
        ])->assertRedirect(route('customer.addresses'));

        $address = CustomerAddress::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertTrue($address->is_default);

        $this->actingAs($other)->delete(route('customer.addresses.destroy', $address))
            ->assertNotFound();

        $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'user_id' => $owner->id]);
    }

    public function test_administrator_cannot_use_customer_account_area(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('customer.dashboard'))->assertForbidden();
    }
}
