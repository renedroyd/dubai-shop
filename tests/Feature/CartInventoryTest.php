<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Producto de prueba',
            'slug' => 'producto-prueba-'.uniqid(),
            'sku' => 'SKU-'.strtoupper(uniqid()),
            'price_amount' => 1250,
            'currency' => 'USD',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ], $attributes));
    }

    public function test_customer_can_add_product_to_session_cart(): void
    {
        $this->withoutVite();
        $product = $this->product();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect(route('cart.index'));

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price_amount' => 1250,
            'currency' => 'USD',
        ]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Producto de prueba')
            ->assertSee('25.00 USD');
    }

    public function test_product_card_displays_available_stock_after_reservations(): void
    {
        $this->withoutVite();
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 9, reason: 'Carga inicial');
        $inventory->update(['quantity_reserved' => 4]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Producto de prueba')
            ->assertSee('5')
            ->assertSee('disponibles');
    }

    public function test_product_card_displays_out_of_stock_when_all_units_are_reserved(): void
    {
        $this->withoutVite();
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 3, reason: 'Carga inicial');
        $inventory->update(['quantity_reserved' => 3]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Agotado');
    }

    public function test_product_card_aggregates_available_stock_across_active_variants(): void
    {
        $this->withoutVite();
        $product = $this->product();
        $small = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Pequeño',
            'sku' => 'SMALL-'.uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $large = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Grande',
            'sku' => 'LARGE-'.uniqid(),
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $smallStock = app(InventoryService::class)->setOnHand($product, 5, $small, reason: 'Carga inicial');
        $smallStock->update(['quantity_reserved' => 2]);
        app(InventoryService::class)->setOnHand($product, 4, $large, reason: 'Carga inicial');

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('7')
            ->assertSee('disponibles');
    }

    public function test_product_card_does_not_claim_zero_stock_when_inventory_is_not_tracked(): void
    {
        $this->withoutVite();
        $this->product();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Consultar disponibilidad')
            ->assertDontSee('Agotado');
    }

    public function test_cart_rejects_products_that_are_not_published_and_visible(): void
    {
        $this->withoutVite();
        $product = $this->product(['status' => Product::STATUS_DRAFT]);

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertNotFound();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_checks_tracked_inventory_before_adding_quantity(): void
    {
        $this->withoutVite();
        $product = $this->product();
        app(InventoryService::class)->setOnHand($product, 2, reason: 'Carga inicial');

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('inventories', [
            'product_id' => $product->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_inventory_adjustments_are_audited_with_signed_quantity_delta(): void
    {
        $product = $this->product();
        $inventory = app(InventoryService::class)->setOnHand($product, 8, reason: 'Recepción');
        app(InventoryService::class)->setOnHand($product, 5, reason: 'Corrección');

        $this->assertSame(5, $inventory->fresh()->quantity_on_hand);
        $this->assertSame(5, $inventory->fresh()->available_quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'adjustment',
            'quantity_delta' => 8,
            'reason' => 'Recepción',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'adjustment',
            'quantity_delta' => -3,
            'reason' => 'Corrección',
        ]);
    }

    public function test_cart_does_not_mix_product_currencies(): void
    {
        $this->withoutVite();
        $usd = $this->product();
        $eur = $this->product([
            'name' => 'Producto en euros',
            'currency' => 'EUR',
        ]);

        $this->post(route('cart.items.store'), [
            'product_id' => $usd->id,
            'quantity' => 1,
        ])->assertRedirect(route('cart.index'));

        $this->post(route('cart.items.store'), [
            'product_id' => $eur->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_customer_can_update_and_remove_an_item_from_their_cart(): void
    {
        $this->withoutVite();
        $product = $this->product();

        $this->post(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cartItem = CartItem::query()->firstOrFail();

        $this->patch(route('cart.items.update', $cartItem), ['quantity' => 3])
            ->assertRedirect(route('cart.index'));

        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'quantity' => 3]);

        $this->delete(route('cart.items.destroy', $cartItem))
            ->assertRedirect(route('cart.index'));

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }
}
