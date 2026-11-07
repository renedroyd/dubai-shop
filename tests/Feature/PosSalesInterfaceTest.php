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
            ->set('terminalId', $terminal->id)
            ->call('openCashSession')
            ->assertForbidden();
    }

    public function test_cashier_can_open_a_cash_session_using_the_selected_terminal_property(): void
    {
        $user = $this->cashier();
        $terminal = $this->terminal();

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->set('terminalId', $terminal->id)
            ->set('openingCurrency', 'CUP')
            ->set('openingAmount', '500.00')
            ->call('openCashSession')
            ->assertSet('cashSessionId', fn ($id) => $id !== null)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pos_cash_sessions', [
            'pos_terminal_id' => $terminal->id,
            'opened_by' => $user->id,
            'status' => 'open',
            'currency' => 'CUP',
            'opening_cash_amount' => 50000,
        ]);
    }

    public function test_cashier_can_search_and_select_a_registered_customer(): void
    {
        $user = $this->cashier();
        $customer = User::factory()->create([
            'name' => 'Cliente Frecuente',
            'email' => 'frecuente@example.com',
            'is_admin' => false,
            'admin_access_enabled' => false,
        ]);
        $terminal = $this->terminal();
        app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->set('customerSearch', 'frecuente')
            ->assertSee('Cliente Frecuente')
            ->call('selectCustomer', $customer->id)
            ->assertSet('customerUserId', $customer->id)
            ->assertSet('customerName', 'Cliente Frecuente')
            ->assertSet('customerEmail', 'frecuente@example.com');
    }

    public function test_cashier_can_find_a_customer_by_saved_phone_number(): void
    {
        $user = $this->cashier();
        $customer = User::factory()->create([
            'name' => 'Cliente por teléfono',
            'email' => 'telefono@example.com',
            'is_admin' => false,
            'admin_access_enabled' => false,
        ]);
        $customer->customerAddresses()->create([
            'recipient_name' => $customer->name,
            'phone' => '555-7788',
            'address' => 'Calle 1',
            'city' => 'Guantánamo',
            'region' => 'Guantánamo',
        ]);
        $terminal = $this->terminal();
        app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->set('customerSearch', '555-7788')
            ->assertSee('Cliente por teléfono')
            ->call('selectCustomer', $customer->id)
            ->assertSet('customerUserId', $customer->id);
    }

    public function test_cashier_can_register_a_customer_from_the_pos_interface(): void
    {
        $user = $this->cashier();
        $terminal = $this->terminal();
        app(PosCashSessionService::class)->open($terminal, $user->id, 'CUP', 5000);

        Livewire::actingAs($user)
            ->test(PosSales::class)
            ->set('customerName', 'Nuevo cliente POS')
            ->set('customerEmail', 'NUEVO@example.com')
            ->set('customerPhone', '555-0123')
            ->call('registerCustomer')
            ->assertHasNoErrors()
            ->assertSet('customerUserId', fn ($id) => $id !== null)
            ->assertSet('customerEmail', 'nuevo@example.com');

        $this->assertDatabaseHas('users', [
            'name' => 'Nuevo cliente POS',
            'email' => 'nuevo@example.com',
            'is_admin' => false,
            'admin_access_enabled' => false,
        ]);
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
