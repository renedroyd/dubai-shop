<?php

namespace App\Services\Storefront;

use App\Models\StorefrontSetting;
use Illuminate\Support\Facades\Cache;

class StorefrontSettings
{
    /** @return array<string, string|null> */
    public function all(): array
    {
        return Cache::remember('storefront.settings', now()->addMinutes(10), fn (): array =>
            StorefrontSetting::query()->where('is_public', true)->pluck('value', 'key')->all()
        );
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }
}
