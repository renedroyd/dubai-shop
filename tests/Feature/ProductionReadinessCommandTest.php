<?php

namespace Tests\\Feature;

use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\File;
use Mockery;
use PDO;
use Tests\\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    public function test_production_readiness_command_passes_with_secure_persistent_configuration(): void
    {
        File::ensureDirectoryExists(storage_path('app/private'));
        File::ensureDirectoryExists(storage_path('app/public'));

        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://shop.example.test',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.trusted_proxies' => ['10.0.0.1'],
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'mail.default' => 'smtp',
            'mail.from.address' => 'orders@shop.example.test',
        ]);

        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andReturn(new PDO('sqlite::memory:'));
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->artisan('app:production-readiness')
            ->expectsOutputToContain('passed the production readiness checks')
            ->assertSuccessful();
    }

    public function test_production_readiness_command_fails_for_insecure_or_non_persistent_configuration(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => true,
            'app.url' => 'http://localhost',
            'app.key' => '',
            'app.trusted_proxies' => ['*'],
            'session.secure' => false,
            'session.http_only' => false,
            'session.encrypt' => false,
            'session.same_site' => 'none',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'mail.default' => 'log',
            'mail.from.address' => 'hello@example.com',
        ]);

        DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('Database unavailable'));

        $this->artisan('app:production-readiness')
            ->expectsOutputToContain('found')
            ->assertFailed();
    }
}
