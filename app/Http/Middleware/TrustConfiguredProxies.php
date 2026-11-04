<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

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

        if (in_array('*', $trustedIps, true) || in_array('**', $trustedIps, true)) {
            throw new \InvalidArgumentException('TRUSTED_PROXIES must list explicit proxy IP addresses or CIDRs.');
        }

        $this->setTrustedProxyIpAddressesToSpecificIps($request, $trustedIps);
    }
}
