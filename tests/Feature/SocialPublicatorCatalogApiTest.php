<?php

namespace Tests\Feature;

use App\Models\IntegrationApiToken;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SocialPublicatorCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(array $abilities = ['catalog:read'], array $attributes = []): string
    {
        $plainTextToken = Str::random(64);

        IntegrationApiToken::query()->create(array_merge([
            'name' => 'SocialPublicator test',
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => now()->addDay(),
        ], $attributes));

        return $plainTextToken;
    }

    private function product(array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Producto API',
            'slug' => 'producto-api-'.Str::lower(Str::random(8)),
            'sku' => 'API-'.Str::upper(Str::random(8)),
            'price_amount' => 159900,
            'currency' => 'CUP',
            'status' => Product::STATUS_ACTIVE,
            'is_visible' => true,
        ], $attributes));
    }

    public function test_catalog_api_requires_a_dedicated_bearer_token(): void
    {
        $this->getJson('/api/integrations/social-publicator/v1/items')
            ->assertUnauthorized();

        $this->withToken('invalid-token')
            ->getJson('/api/integrations/social-publicator/v1/items')
            ->assertUnauthorized();
    }

    public function test_catalog_api_rejects_tokens_without_catalog_read_ability(): void
    {
        $this->withToken($this->token(['inventory:read']))
            ->getJson('/api/integrations/social-publicator/v1/items')
            ->assertForbidden();
    }

    public function test_catalog_api_exposes_only_published_products_and_online_eligible_warehouse_availability(): void
    {
        $warehouse = Warehouse::primary();
        $warehouse->update(['online_fulfillment_enabled' => true]);

        $disabledWarehouse = Warehouse::query()->create([
            'code' => 'NO-WEB-'.Str::upper(Str::random(5)),
            'name' => 'Almacén no online',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
            'online_fulfillment_enabled' => false,
        ]);

        $product = $this->product();
        $inventoryService = app(InventoryService::class);
        $inventory = $inventoryService->setOnHand($product, 10, reason: 'API test', warehouse: $warehouse);
        $inventory->update(['quantity_reserved' => 2, 'quantity_blocked' => 1]);
        $inventoryService->setOnHand($product, 50, reason: 'API test', warehouse: $disabledWarehouse);

        $this->product(['status' => Product::STATUS_DRAFT]);
        $this->product(['is_visible' => false]);

        $this->withToken($this->token())
            ->getJson('/api/integrations/social-publicator/v1/items')
            ->assertOk()
            ->assertJsonPath('contract_version', 'v1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', $product->external_id)
            ->assertJsonPath('data.0.item_type', 'product')
            ->assertJsonPath('data.0.price.amount_minor', 159900)
            ->assertJsonPath('data.0.price.currency', 'CUP')
            ->assertJsonPath('data.0.availability.warehouses.0.warehouse_external_id', $warehouse->external_id)
            ->assertJsonPath('data.0.availability.warehouses.0.available_quantity', 7)
            ->assertJsonMissingPath('data.0.acquisition_cost_amount')
            ->assertJsonMissingPath('data.0.minimum_sale_price_amount');
    }

    public function test_variant_api_uses_variant_price_and_only_active_variants(): void
    {
        $warehouse = Warehouse::primary();
        $warehouse->update(['online_fulfillment_enabled' => true]);
        $product = $this->product(['price_amount' => 20000]);

        $activeVariant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Presentación grande',
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'price_amount' => 25000,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Variante inactiva',
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'is_active' => false,
            'sort_order' => 2,
        ]);

        app(InventoryService::class)->setOnHand($product, 6, variant: $activeVariant, reason: 'API test', warehouse: $warehouse);

        $this->withToken($this->token())
            ->getJson('/api/integrations/social-publicator/v1/items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', $activeVariant->external_id)
            ->assertJsonPath('data.0.item_type', 'variant')
            ->assertJsonPath('data.0.price.amount_minor', 25000)
            ->assertJsonPath('data.0.availability.warehouses.0.available_quantity', 6);
    }

    public function test_catalog_item_can_be_retrieved_by_external_id(): void
    {
        $product = $this->product();

        $this->withToken($this->token())
            ->getJson('/api/integrations/social-publicator/v1/items/'.$product->external_id)
            ->assertOk()
            ->assertJsonPath('data.external_id', $product->external_id);
    }

    public function test_warehouse_endpoint_returns_only_active_online_fulfillment_warehouses(): void
    {
        $primary = Warehouse::primary();
        $primary->update(['online_fulfillment_enabled' => true]);

        Warehouse::query()->create([
            'code' => 'OFFLINE-'.Str::upper(Str::random(5)),
            'name' => 'Almacén no habilitado',
            'type' => 'general',
            'is_primary' => false,
            'is_active' => true,
            'online_fulfillment_enabled' => false,
        ]);

        $this->withToken($this->token())
            ->getJson('/api/integrations/social-publicator/v1/warehouses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_id', $primary->external_id);
    }

    public function test_catalog_items_are_cursor_paginated(): void
    {
        $this->product(['name' => 'Producto uno', 'sku' => 'API-PAGE-1']);
        $this->product(['name' => 'Producto dos', 'sku' => 'API-PAGE-2']);

        $token = $this->token();

        $firstPage = $this->withToken($token)
            ->getJson('/api/integrations/social-publicator/v1/items?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('has_more', true);

        $cursor = $firstPage->json('next_cursor');

        $this->withToken($token)
            ->getJson('/api/integrations/social-publicator/v1/items?limit=1&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('has_more', false);
    }

    public function test_expired_and_revoked_tokens_are_rejected(): void
    {
        $expired = $this->token(['catalog:read'], ['expires_at' => now()->subMinute()]);
        $revoked = $this->token(['catalog:read'], ['revoked_at' => now()]);

        $this->withToken($expired)->getJson('/api/integrations/social-publicator/v1/items')->assertUnauthorized();
        $this->withToken($revoked)->getJson('/api/integrations/social-publicator/v1/items')->assertUnauthorized();
    }
}
