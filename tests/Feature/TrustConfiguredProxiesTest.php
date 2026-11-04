<?php

namespace Tests\\Feature;

use App\\Http\\Middleware\\TrustConfiguredProxies;
use Illuminate\\Http\\Request;
use PHPUnit\\Framework\\Attributes\\DataProvider;
use Tests\\TestCase;

class TrustConfiguredProxiesTest extends TestCase
{
    public function test_wildcard_trusts_all_proxy_addresses_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        config(['app.trusted_proxies' => ['*']]);

        $request = Request::create('http://shop.test', 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustConfiguredProxies())->handle($request, fn (Request $request) => response('ok'));

        $this->assertSame(['0.0.0.0/0', '::/0'], Request::getTrustedProxies());
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_wildcard_is_rejected_outside_local_environment(string $environment): void
    {
        $this->app['env'] = $environment;
        config(['app.trusted_proxies' => ['*']]);

        $request = Request::create('http://shop.test', 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.10',
        ]);

        $this->expectException(\\InvalidArgumentException::class);
        $this->expectExceptionMessage('APP_ENV=local');

        (new TrustConfiguredProxies())->handle($request, fn (Request $request) => response('ok'));
    }

    public static function nonLocalEnvironments(): array
    {
        return [
            'production' => ['production'],
            'testing' => ['testing'],
            'staging' => ['staging'],
        ];
    }

    public function test_double_wildcard_is_rejected_even_in_local_environment(): void
    {
        $this->app['env'] = 'local';
        config(['app.trusted_proxies' => ['**']]);

        $request = Request::create('http://shop.test', 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.10',
        ]);

        $this->expectException(\\InvalidArgumentException::class);
        $this->expectExceptionMessage('TRUSTED_PROXIES=**');

        (new TrustConfiguredProxies())->handle($request, fn (Request $request) => response('ok'));
    }

    public function test_wildcard_cannot_be_combined_with_explicit_proxy_addresses(): void
    {
        $this->app['env'] = 'local';
        config(['app.trusted_proxies' => ['*', '192.0.2.1']]);

        $request = Request::create('http://shop.test', 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.10',
        ]);

        $this->expectException(\\InvalidArgumentException::class);
        $this->expectExceptionMessage('allowed only by itself');

        (new TrustConfiguredProxies())->handle($request, fn (Request $request) => response('ok'));
    }
}
