# SocialPublicator API v1 — Dubai Shop

## Endpoints

All routes require HTTPS and a dedicated Bearer token with the `catalog:read` ability. The API applies a per-IP limit of 60 requests per minute.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/integrations/social-publicator/v1/items` | Published catalog items with cursor pagination. |
| GET | `/api/integrations/social-publicator/v1/items/{external_id}` | Retrieve one published product or active variant. |
| GET | `/api/integrations/social-publicator/v1/warehouses` | Active warehouses enabled for online fulfillment. |

### Pagination

`GET /items?limit=50` returns `data`, `next_cursor` and `has_more`. The limit is constrained to 1–100. Pass the returned cursor unchanged to continue:

`GET /items?limit=50&cursor={next_cursor}`

The cursor is opaque and ordered by product update time and ID. Product revisions increase when product data, variants, product images, inventory, or online-fulfillment warehouse settings change. This makes the cursor suitable for incremental catalog polling. Inventory and sale-price changes also create versioned integration events in the transactional outbox.

### Catalog representation

- A simple product is represented by its product external ID.
- A product with variants is represented by one item per active variant; inactive variants are omitted.
- Variant price overrides the product sale price when present; otherwise the product price is used.
- Variant inventory uses variant-specific balances when present and follows the existing storefront fallback to product-level balances when no variant balance exists.
- Availability is returned for every currently eligible warehouse, including zero balances.
- Availability excludes reserved and blocked quantities.
- Prices are integer minor units and include the product's ISO currency code.
- Acquisition cost and internal minimum sale price are never serialized.

### Token issuance

Run this command on the Dubai Shop application server:

```bash
php artisan social-publicator:token --name="SocialPublicator" --expires=365
```

The command prints the plaintext token once. Store it in the SocialPublicator secret manager or environment as `DUBAI_SHOP_API_TOKEN`. The database stores only its SHA-256 hash. Use `--expires=0` only when a non-expiring credential is explicitly required.

Tokens can be revoked by setting `revoked_at` in the `integration_api_tokens` table. Expired or revoked tokens receive HTTP 401; tokens without the required ability receive HTTP 403.

## Current scope

This phase implements read-only catalog and warehouse access plus stable external IDs. Signed event delivery from the outbox is a subsequent phase. Outbox rows are created in the same transaction as catalog, variant, and inventory model writes; no outbound network request runs inside the business transaction. The API does not expose customer, order, payment, acquisition-cost, or internal minimum-sale-price data.


## Lifecycle event outbox

Dubai Shop writes `inventory.changed` and `price.changed` v1 envelopes to `integration_event_outbox`. Inventory events are emitted for the external product or active variant whose effective stock changed. Price events contain only public sale-price amounts and currency; acquisition costs and internal minimum sale prices are never included. Outbox records remain pending until the signed delivery worker is introduced in the publication phase.