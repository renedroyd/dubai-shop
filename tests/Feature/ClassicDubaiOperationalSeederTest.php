<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\ShippingZone;
use App\Models\StorefrontSetting;
use App\Services\Shipping\ShippingRateService;
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

    public function test_it_resolves_temporary_rates_when_municipality_is_typed_without_accents(): void
    {
        $this->seed(ClassicDubaiOperationalSeeder::class);

        $service = app(ShippingRateService::class);

        foreach ([
            'Guantanamo',
            'Maisi',
            'Niceto Perez',
            'Imias',
        ] as $municipality) {
            $zone = $service->resolve([
                'shipping_country' => 'CU',
                'shipping_region' => 'Guantánamo',
                'shipping_city' => $municipality,
                'shipping_locality' => '',
            ], 'CUP');

            $this->assertSame(500.0, (float) $zone->shipping_amount);
            $this->assertSame('CUP', $zone->currency);
        }
    }

    public function test_it_keeps_municipality_matching_strict_after_normalization(): void
    {
        $this->seed(ClassicDubaiOperationalSeeder::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ShippingRateService::class)->resolve([
            'shipping_country' => 'CU',
            'shipping_region' => 'Guantánamo',
            'shipping_city' => 'Municipio inexistente',
            'shipping_locality' => '',
        ], 'CUP');
    }
}
