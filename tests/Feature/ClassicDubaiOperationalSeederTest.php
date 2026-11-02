<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\ShippingZone;
use App\Models\StorefrontSetting;
use Database\Seeders\ClassicDubaiOperationalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassicDubaiOperationalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_store_identity_cash_on_delivery_and_temporary_shipping_rates(): void
    {
        $this->seed(ClassicDubaiOperationalSeeder::class);

        $this->assertSame(
            'Clásico Dubai',
            StorefrontSetting::query()->where('key', 'site_name')->value('value'),
        );

        $this->assertTrue(
            PaymentMethod::query()->where('code', 'cash-on-delivery')->value('is_enabled'),
        );

        $this->assertSame(10, ShippingZone::query()
            ->where('province', 'Guantánamo')
            ->whereNull('locality')
            ->where('currency', 'CUP')
            ->where('shipping_amount', 500)
            ->where('is_active', true)
            ->count());
    }
}
