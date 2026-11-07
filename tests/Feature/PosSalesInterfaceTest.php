<?php

namespace Tests\Feature;

use App\Filament\Pages\PosSales;
use App\Models\AdminRole;
use App\Models\PaymentMethod;
use App\Models\PosLocation;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseUserAccess;
use App\Services\Inventory\InventoryService;
use App\Services\Pos\PosCashSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosSalesInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function cashier(): User
    {
        $user = User::factory()->create();
        $user->update(['admin_access_enabled' => true]);
        $user->roles()->attach(AdminRole::query()->where('slug', 'pos-cashier')->firstOrFail());
        $user->warehouseAccesses()->create([
            'warehouse_id' => Warehouse::primary()->id,
            'permissions' => WarehouseUserAccess::PERMISSIONS,
        ]);

        return $user;
    }

    private function terminal(): PosTerminal
    {
        $location = PosLocation::query()->create(['code' => 'POS-UI', 'name' => 'Tienda POS', 'is_active' => true]);

        return PosTerminal::query()->create([
            'pos_location_id' => $location->id,
            'warehouse_id' => Warehouse::primary()->id,
            'code' => 'POS-UI-01',
            'name' => 'Caja POS',
            'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Producto interfaz POS',
            'slug' => 'producto-interfaz-pos',
            'sku' => 'POS-UI-001',
            'price_amount' => 1250,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ]);
    }

    public function test_cashier_can_open_the_pos_interface_and_load_their_session_products(): void
    {
        $this->withoutVite();
        $user = $this->cashier();
        $terminal = $this->terminal();
        app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 6, reason: 'Carga inicial');

        $this->actingAs($user)->get('/admin/pos-sales')->assertOk();

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->assertSee('Terminal de ventas')
            ->assertSee('Producto interfaz POS')
            ->assertSee('Caja POS');
    }

    public function test_user_with_only_view_permission_cannot_open_a_cash_session_from_the_sales_interface(): void
    {
        $role = AdminRole::query()->create([
            'name' => 'POS solo consulta',
            'slug' => 'pos-view-only',
            'permissions' => ['panel.access', 'pos_cash_sessions.view'],
        ]);
        $user = User::factory()->create(['admin_access_enabled' => true]);
        $user->roles()->attach($role);
        $user->warehouseAccesses()->create([
            'warehouse_id' => Warehouse::primary()->id,
            'permissions' => WarehouseUserAccess::PERMISSIONS,
        ]);
        $terminal = $this->terminal();

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->call('openCashSession', $terminal->id)
            ->assertForbidden();
    }

    public function test_cashier_can_register_a_sale_through_the_professional_interface_using_existing_services(): void
    {
        $user = $this->cashier();
        $terminal = $this->terminal();
        $session = app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 6, reason: 'Carga inicial');
        $method = PaymentMethod::query()->where('driver', 'cash_on_delivery')->where('is_enabled', true)->firstOrFail();

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->call('addProduct', $product->id)
            ->assertSet('cart.'.$product->id.':0.quantity', 1)
            ->set('paymentMethodId', $method->id)
            ->call('completeSale')
            ->assertSet('cart', [])
            ->assertSet('lastOrderNumber', fn ($number) => str_starts_with($number, 'POS-'));

        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('orders', [
            'channel' => 'pos',
            'pos_cash_session_id' => $session->id,
            'total_amount' => 1250,
            'payment_status' => 'paid',
        ]);
    }
}
