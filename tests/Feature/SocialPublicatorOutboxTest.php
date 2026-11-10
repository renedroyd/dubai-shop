<?php

namespace Tests\Feature;

use App\Models\IntegrationEventOutbox;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SocialPublicatorOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Producto de integración',
            'slug' => 'producto-integracion-'.Str::lower(Str::random(8)),
            'sku' => 'OUTBOX-'.Str::upper(Str::random(8)),
            'price_amount' => 159900,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ], $attributes));
    }

    public function test_inventory_changes_are_written_to_outbox_for_external_catalog_items(): void
    {
        $warehouse = Warehouse::primary();
        $warehouse->update(['online_fulfillment_enabled' => true]);
        $product = $this->product();

        app(InventoryService::class)->setOnHand($product, 5, reason: 'Outbox test', warehouse: $warehouse);

        $event = IntegrationEventOutbox::query()
            ->where('event_type', 'inventory.changed')
            ->where('aggregate_external_id', $product->external_id)
            ->firstOrFail();

        $this->assertSame($event->event_id, $event->payload['event_id']);
        $this->assertSame($product->external_id, $event->payload['data']['item_external_id']);
        $this->assertSame('v1', $event->payload['contract_version']);
        $this->assertNull($event->delivered_at);
    }

    public function test_product_price_changes_are_written_to_outbox_with_public_price_values(): void
    {
        $product = $this->product();
        $product->update(['price_amount' => 179900]);

        $event = IntegrationEventOutbox::query()
            ->where('event_type', 'price.changed')
            ->where('aggregate_external_id', $product->external_id)
            ->firstOrFail();

        $this->assertSame(159900, $event->payload['data']['previous_price']['amount_minor']);
        $this->assertSame(179900, $event->payload['data']['current_price']['amount_minor']);
        $this->assertSame('CUP', $event->payload['data']['current_price']['currency']);
    }

    public function test_variant_price_changes_use_the_variant_external_identifier(): void
    {
        $product = $this->product();
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Variante de prueba',
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'price_amount' => 200000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $variant->update(['price_amount' => 210000]);

        $this->assertDatabaseHas('integration_event_outbox', [
            'event_type' => 'price.changed',
            'aggregate_external_id' => $variant->external_id,
        ]);
    }

    public function test_outbox_event_rolls_back_with_the_inventory_transaction(): void
    {
        $warehouse = Warehouse::primary();
        $warehouse->update(['online_fulfillment_enabled' => true]);
        $product = $this->product();

        try {
            DB::transaction(function () use ($product, $warehouse): void {
                app(InventoryService::class)->setOnHand($product, 8, reason: 'Rollback test', warehouse: $warehouse);

                throw new RuntimeException('Force transaction rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force transaction rollback', $exception->getMessage());
        }

        $this->assertDatabaseMissing('integration_event_outbox', [
            'event_type' => 'inventory.changed',
            'aggregate_external_id' => $product->external_id,
        ]);
    }
}
