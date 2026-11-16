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
}
