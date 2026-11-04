<?php

namespace App\\Http\\Middleware;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Middleware\\TrustProxies as Middleware;

class TrustConfiguredProxies extends Middleware
{
    protected function setTrustedProxyIpAddresses(Request $request)
    {
        $trustedIps = config('app.trusted_proxies', []);

        if (is_string($trustedIps)) {
            $trustedIps = array_filter(array_map('trim', explode(',', $trustedIps)));
        }

        if (! is_array($trustedIps) || $trustedIps === []) {
            return;
        }

        if (in_array('**', $trustedIps, true)) {
            throw new \\InvalidArgumentException('TRUSTED_PROXIES=** is not supported. Use explicit proxy IP addresses or CIDRs.');
        }

        if (in_array('*', $trustedIps, true)) {
            if (count($trustedIps) === 1 && app()->environment('local')) {
                $this->setTrustedProxyIpAddressesToTheCallingIp($request);

                return;
            }

            throw new \\InvalidArgumentException('TRUSTED_PROXIES=* is allowed only by itself when APP_ENV=local. Use explicit proxy IP addresses or CIDRs in every other environment.');
        }

        $this->setTrustedProxyIpAddressesToSpecificIps($request, $trustedIps);
    }
}
