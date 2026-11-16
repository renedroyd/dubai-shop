<?php

namespace Tests\Feature;

use App\Models\IntegrationApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DubaiShopInstallationIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_catalog_token_can_read_stable_installation_identity(): void
    {
        $plainTextToken = Str::random(64);

        IntegrationApiToken::query()->create([
            'name' => 'SocialPublicator read-only',
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities' => ['catalog:read'],
        ]);

        $expectedIdentity = DB::table('integration_installation_identity')
            ->where('id', 1)
            ->value('installation_id');

        $this->withToken($plainTextToken)
            ->getJson('/api/integrations/social-publicator/v1/identity')
            ->assertOk()
            ->assertJsonPath('contract_version', 'v1')
            ->assertJsonPath('installation_id', $expectedIdentity);

        $this->withToken($plainTextToken)
            ->getJson('/api/integrations/social-publicator/v1/identity')
            ->assertOk()
            ->assertJsonPath('installation_id', $expectedIdentity);

        $this->assertTrue(Str::isUuid($expectedIdentity));
    }

    public function test_identity_endpoint_rejects_missing_or_invalid_token(): void
    {
        $this->getJson('/api/integrations/social-publicator/v1/identity')
            ->assertUnauthorized();

        IntegrationApiToken::query()->create([
            'name' => 'Revoked token',
            'token_hash' => hash('sha256', 'revoked-token'),
            'abilities' => ['catalog:read'],
            'revoked_at' => now(),
        ]);

        $this->withToken('revoked-token')
            ->getJson('/api/integrations/social-publicator/v1/identity')
            ->assertUnauthorized();
    }

    public function test_clone_rotation_requires_both_explicit_confirmation_options(): void
    {
        $before = DB::table('integration_installation_identity')->where('id', 1)->value('installation_id');

        $this->artisan('social-publicator:rotate-installation-identity')
            ->assertFailed();

        $this->assertSame(
            $before,
            DB::table('integration_installation_identity')->where('id', 1)->value('installation_id'),
        );
    }

    public function test_clone_rotation_changes_identity_revokes_old_tokens_and_quarantines_undelivered_events(): void
    {
        $oldToken = Str::random(64);
        IntegrationApiToken::query()->create([
            'name' => 'Original workspace token',
            'token_hash' => hash('sha256', $oldToken),
            'abilities' => ['catalog:read'],
        ]);

        $oldIdentity = DB::table('integration_installation_identity')->where('id', 1)->value('installation_id');

        DB::table('integration_event_outbox')->insert([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'inventory.changed',
            'aggregate_type' => 'catalog_item',
            'aggregate_external_id' => (string) Str::uuid(),
            'revision' => 1,
            'payload' => json_encode(['event_id' => (string) Str::uuid()]),
            'occurred_at' => now(),
            'attempts' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('social-publicator:rotate-installation-identity --as-new-installation --confirm')
            ->assertSuccessful();

        $identity = DB::table('integration_installation_identity')->where('id', 1)->first();

        $this->assertNotSame($oldIdentity, $identity->installation_id);
        $this->assertSame('pending', $identity->pairing_status);
        $this->assertSame('clone-', substr((string) $identity->webhook_key_id, 0, 6));
        $this->assertNotSame('', $identity->webhook_secret);
        $this->assertNotSame($identity->webhook_secret, \Illuminate\Support\Facades\Crypt::decryptString($identity->webhook_secret));

        $this->assertNotNull(IntegrationApiToken::query()->where('token_hash', hash('sha256', $oldToken))->value('revoked_at'));
        $this->assertSame(1, IntegrationApiToken::query()->whereNull('revoked_at')->count());

        $event = DB::table('integration_event_outbox')->first();
        $this->assertNotNull($event->failed_at);
        $this->assertNull($event->available_at);
        $this->assertNull($event->locked_at);
        $this->assertStringContainsString('not replayed', $event->last_error);
    }

    public function test_outbox_delivery_is_held_while_installation_is_awaiting_pairing(): void
    {
        config([
            'services.social_publicator.enabled' => true,
            'services.social_publicator.webhook_url' => 'https://social.example.test/events',
            'services.social_publicator.webhook_key_id' => 'legacy-key',
            'services.social_publicator.webhook_secret' => 'legacy-secret',
        ]);

        DB::table('integration_installation_identity')->where('id', 1)->update(['pairing_status' => 'pending']);

        $eventId = (string) Str::uuid();
        $outboxId = DB::table('integration_event_outbox')->insertGetId([
            'event_id' => $eventId,
            'event_type' => 'inventory.changed',
            'aggregate_type' => 'catalog_item',
            'aggregate_external_id' => (string) Str::uuid(),
            'revision' => 1,
            'payload' => json_encode(['event_id' => $eventId]),
            'occurred_at' => now(),
            'attempts' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Http::fake();

        $this->artisan('social-publicator:dispatch-outbox')->assertSuccessful();

        (new \App\Jobs\DeliverIntegrationEventOutbox($outboxId))->handle();

        $event = DB::table('integration_event_outbox')->where('id', $outboxId)->first();
        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->locked_at);
        $this->assertNull($event->delivered_at);
        $this->assertNull($event->failed_at);
        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_pairing_can_only_be_completed_for_the_current_identity_with_confirmation(): void
    {
        $installationId = (string) DB::table('integration_installation_identity')->where('id', 1)->value('installation_id');

        DB::table('integration_installation_identity')->where('id', 1)->update([
            'pairing_status' => 'pending',
            'webhook_key_id' => 'new-key',
            'webhook_secret' => \Illuminate\Support\Facades\Crypt::encryptString('new-secret'),
        ]);

        $this->artisan('social-publicator:complete-installation-pairing '.(string) Str::uuid().' --confirm')
            ->assertFailed();
        $this->assertSame('pending', DB::table('integration_installation_identity')->where('id', 1)->value('pairing_status'));

        $this->artisan('social-publicator:complete-installation-pairing '.$installationId)
            ->assertFailed();
        $this->assertSame('pending', DB::table('integration_installation_identity')->where('id', 1)->value('pairing_status'));

        $this->artisan('social-publicator:complete-installation-pairing '.$installationId.' --confirm')
            ->assertSuccessful();
        $this->assertSame('paired', DB::table('integration_installation_identity')->where('id', 1)->value('pairing_status'));
    }

}
