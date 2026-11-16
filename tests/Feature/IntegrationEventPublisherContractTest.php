<?php

namespace Tests\Feature;

use App\Services\IntegrationEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationEventPublisherContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_event_publisher_emits_the_shared_v1_contract_shape(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/integration-contracts/dubai-shop-inventory-changed-v1.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $event = app(IntegrationEventPublisher::class)->publish(
            $fixture['event_type'],
            'catalog_item',
            $fixture['aggregate_external_id'],
            $fixture['revision'],
            $fixture['data'],
        );

        $payload = $event->payload;

        $this->assertSame($fixture['event_type'], $payload['event_type']);
        $this->assertSame($fixture['contract_version'], $payload['contract_version']);
        $this->assertSame($fixture['aggregate_external_id'], $payload['aggregate_external_id']);
        $this->assertSame($fixture['revision'], $payload['revision']);
        $this->assertSame($fixture['data']['item_external_id'], $payload['data']['item_external_id']);
        $this->assertSame($fixture['data']['availability'], $payload['data']['availability']);
        $this->assertSame($event->event_id, $payload['event_id']);
        $this->assertSame($payload['event_id'], $event->event_id);
        $this->assertNotEmpty($payload['occurred_at']);
        $this->assertSame($payload['event_id'], $event->event_id);
    }
}
