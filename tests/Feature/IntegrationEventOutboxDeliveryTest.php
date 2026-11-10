<?php

namespace Tests\Feature;

use App\Jobs\DeliverIntegrationEventOutbox;
use App\Models\IntegrationEventOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationEventOutboxDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attributes = []): IntegrationEventOutbox
    {
        return IntegrationEventOutbox::query()->create(array_merge([
            'event_id' => '8f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'event_type' => 'inventory.changed',
            'aggregate_type' => 'catalog_item',
            'aggregate_external_id' => '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'revision' => 2,
            'payload' => [
                'event_id' => '8f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
                'event_type' => 'inventory.changed',
                'contract_version' => 'v1',
                'aggregate_external_id' => '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00',
                'revision' => 2,
                'occurred_at' => now()->toISOString(),
                'data' => ['item_external_id' => '9d20b7e0-01a1-4e33-8a10-40abfa9b2a00'],
            ],
            'occurred_at' => now(),
            'attempts' => 0,
            'available_at' => now(),
        ], $attributes));
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social_publicator.enabled' => true,
            'services.social_publicator.webhook_url' => 'https://social.example.test/api/integrations/dubai-shop/v1/events',
            'services.social_publicator.webhook_key_id' => 'test-key',
            'services.social_publicator.webhook_secret' => 'test-secret-value',
            'services.social_publicator.timeout' => 5,
        ]);
    }

    public function test_delivery_sends_exact_signed_body_and_marks_event_delivered(): void
    {
        Http::fake([
            'https://social.example.test/*' => Http::response(['accepted' => true], 202),
        ]);

        $event = $this->event();

        (new DeliverIntegrationEventOutbox($event->id))->handle();

        $event->refresh();

        $this->assertNotNull($event->delivered_at);
        $this->assertNull($event->available_at);
        $this->assertNull($event->locked_at);
        $this->assertSame(1, $event->attempts);

        Http::assertSent(function (Request $request): bool {
            $timestamp = $request->header('X-DubaiShop-Timestamp')[0] ?? '';
            $signature = hash_hmac('sha256', $timestamp.'.'.$request->body(), 'test-secret-value');

            return $request->url() === 'https://social.example.test/api/integrations/dubai-shop/v1/events'
                && ($request->header('X-DubaiShop-Key-Id')[0] ?? null) === 'test-key'
                && ($request->header('X-DubaiShop-Signature')[0] ?? null) === 'sha256='.$signature;
        });
    }

    public function test_failed_delivery_is_scheduled_for_retry_without_marking_delivered(): void
    {
        Http::fake([
            'https://social.example.test/*' => Http::response(['message' => 'temporarily unavailable'], 503),
        ]);

        $event = $this->event();

        (new DeliverIntegrationEventOutbox($event->id))->handle();

        $event->refresh();

        $this->assertNull($event->delivered_at);
        $this->assertNull($event->failed_at);
        $this->assertNull($event->locked_at);
        $this->assertSame(1, $event->attempts);
        $this->assertTrue($event->available_at->isFuture());
        $this->assertStringContainsString('HTTP 503', $event->last_error);
    }

    public function test_eighth_failed_delivery_moves_event_to_terminal_failure_state(): void
    {
        Http::fake([
            'https://social.example.test/*' => Http::response([], 500),
        ]);

        $event = $this->event(['attempts' => 7]);

        (new DeliverIntegrationEventOutbox($event->id))->handle();

        $event->refresh();

        $this->assertNotNull($event->failed_at);
        $this->assertNull($event->available_at);
        $this->assertSame(8, $event->attempts);
    }

    public function test_dispatch_command_queues_only_due_pending_events(): void
    {
        Bus::fake();

        $due = $this->event();
        $this->event([
            'event_id' => '7f20b7e0-01a1-4e33-8a10-40abfa9b2a00',
            'available_at' => now()->addHour(),
        ]);

        $this->artisan('social-publicator:dispatch-outbox')
            ->assertSuccessful();

        Bus::assertDispatched(DeliverIntegrationEventOutbox::class, fn ($job): bool => $job->outboxId === $due->id);
        $this->assertNotNull($due->fresh()->locked_at);
    }

    public function test_terminal_event_can_be_manually_requeued(): void
    {
        $event = $this->event([
            'attempts' => 8,
            'available_at' => null,
            'failed_at' => now(),
            'last_error' => 'Repeated delivery failure',
        ]);

        $this->artisan('social-publicator:requeue-outbox '.$event->event_id)
            ->assertSuccessful();

        $event->refresh();

        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->failed_at);
        $this->assertNull($event->locked_at);
        $this->assertNull($event->last_error);
        $this->assertNotNull($event->available_at);
    }


    public function test_pending_event_cannot_be_manually_requeued(): void
    {
        $event = $this->event();

        $this->artisan('social-publicator:requeue-outbox '.$event->event_id)
            ->assertFailed();

        $this->assertSame(0, $event->fresh()->attempts);
        $this->assertNull($event->fresh()->failed_at);
    }


    public function test_disabled_delivery_does_not_send_or_consume_attempts(): void
    {
        config(['services.social_publicator.enabled' => false]);
        Http::fake();

        $event = $this->event();

        (new DeliverIntegrationEventOutbox($event->id))->handle();

        $event->refresh();

        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->delivered_at);
        $this->assertNull($event->locked_at);
        $this->assertTrue($event->available_at->isFuture());
        Http::assertNothingSent();
    }

    public function test_disabled_dispatch_command_leaves_due_events_unclaimed(): void
    {
        config(['services.social_publicator.enabled' => false]);
        Bus::fake();

        $event = $this->event();

        $this->artisan('social-publicator:dispatch-outbox')->assertSuccessful();

        Bus::assertNothingDispatched();
        $this->assertNull($event->fresh()->locked_at);
    }

}
