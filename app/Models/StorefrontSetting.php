<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StorefrontSetting extends Model
{
    protected $fillable = ['key', 'group', 'label', 'value', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('storefront.settings'));
        static::deleted(fn () => Cache::forget('storefront.settings'));
    }
}
