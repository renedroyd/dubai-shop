<?php

namespace Tests\Feature;

use App\Models\AdminRole;
use App\Models\PosLoyaltySetting;
use App\Models\PosLoyaltyWallet;
use App\Models\User;
use App\Services\Pos\PosLoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosLoyaltyAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true, 'admin_access_enabled' => true]);
        $admin->roles()->attach(AdminRole::query()->where('slug', 'superadmin')->firstOrFail());

        return $admin;
    }

    public function test_authorized_admin_can_adjust_points_with_required_audit_reason(): void
    {
        $admin = $this->superadmin();
        $customer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);

        $entry = app(PosLoyaltyService::class)->adjustWallet($customer->id, 'CUP', 35, 'Compensación por incidencia aprobada', $admin->id);

        $this->assertSame(35, PosLoyaltyWallet::query()->where('user_id', $customer->id)->where('currency', 'CUP')->firstOrFail()->availablePoints());
        $this->assertDatabaseHas('pos_loyalty_entries', [
            'id' => $entry->id,
            'actor_id' => $admin->id,
            'type' => 'manual_adjustment',
            'points_delta' => 35,
        ]);
        $this->assertSame('Compensación por incidencia aprobada', $entry->fresh()->metadata['reason']);
    }

    public function test_manual_negative_adjustment_cannot_create_negative_balance(): void
    {
        $admin = $this->superadmin();
        $customer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);
        $wallet = PosLoyaltyWallet::query()->create(['user_id' => $customer->id, 'currency' => 'USD', 'points_balance' => 20]);
        $wallet->entries()->create([
            'type' => 'earn',
            'points_delta' => 20,
            'idempotency_key' => 'admin-adjust-seed-'.$customer->id,
            'expires_at' => now()->addMonths(12),
        ]);

        try {
            app(PosLoyaltyService::class)->adjustWallet($customer->id, 'USD', -21, 'Corrección de saldo', $admin->id);
            $this->fail('The adjustment must not reduce the balance below zero.');
        } catch (ValidationException) {
            $this->assertSame(20, $wallet->fresh()->availablePoints());
            $this->assertDatabaseCount('pos_loyalty_entries', 1);
        }
    }

    public function test_user_without_loyalty_adjustment_permission_cannot_change_wallet(): void
    {
        $cashier = User::factory()->create(['is_admin' => true, 'admin_access_enabled' => true]);
        $customer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(PosLoyaltyService::class)->adjustWallet($customer->id, 'EUR', 10, 'Ajuste sin autorización', $cashier->id);
    }

    public function test_customer_dashboard_shows_only_own_balances_and_expiration_warning(): void
    {
        $customer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);
        $otherCustomer = User::factory()->create(['is_admin' => false, 'admin_access_enabled' => false]);

        $wallet = PosLoyaltyWallet::query()->create(['user_id' => $customer->id, 'currency' => 'CUP', 'points_balance' => 75]);
        $wallet->entries()->create([
            'type' => 'earn',
            'points_delta' => 75,
            'idempotency_key' => 'customer-expiring-points-'.$customer->id,
            'expires_at' => now()->addDays(14),
        ]);
        $otherWallet = PosLoyaltyWallet::query()->create(['user_id' => $otherCustomer->id, 'currency' => 'USD', 'points_balance' => 999]);
        $otherWallet->entries()->create([
            'type' => 'earn',
            'points_delta' => 999,
            'idempotency_key' => 'other-customer-points-'.$otherCustomer->id,
            'expires_at' => now()->addMonths(12),
        ]);

        $response = $this->actingAs($customer)->get(route('customer.dashboard'));

        $response->assertOk()
            ->assertSee('Mi programa de fidelización')
            ->assertSee('75')
            ->assertSee('vencen durante los próximos 30 días')
            ->assertDontSee('999');
    }

    public function test_loyalty_setting_rejects_invalid_redemption_percentage(): void
    {
        $setting = PosLoyaltySetting::query()->where('currency', 'CUP')->firstOrFail();

        $this->expectException(ValidationException::class);
        $setting->update(['maximum_redemption_basis_points' => 10001]);
    }
}
