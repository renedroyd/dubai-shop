<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use Illuminate\Validation\ValidationException;

class ShippingRateService
{
    /** @return array<string, string> */
    public static function provinces(): array
    {
        return [
            'Pinar del Río' => 'Pinar del Río',
            'Artemisa' => 'Artemisa',
            'La Habana' => 'La Habana',
            'Mayabeque' => 'Mayabeque',
            'Matanzas' => 'Matanzas',
            'Cienfuegos' => 'Cienfuegos',
            'Villa Clara' => 'Villa Clara',
            'Sancti Spíritus' => 'Sancti Spíritus',
            'Ciego de Ávila' => 'Ciego de Ávila',
            'Camagüey' => 'Camagüey',
            'Las Tunas' => 'Las Tunas',
            'Holguín' => 'Holguín',
            'Granma' => 'Granma',
            'Santiago de Cuba' => 'Santiago de Cuba',
            'Guantánamo' => 'Guantánamo',
            'Isla de la Juventud' => 'Isla de la Juventud',
        ];
    }

    public function resolve(array $address, string $currency): ShippingZone
    {
        if (($address['shipping_country'] ?? null) !== 'CU') {
            throw ValidationException::withMessages([
                'shipping_country' => 'Dubai Shop realiza entregas únicamente dentro de Cuba.',
            ]);
        }

        $municipality = $this->normalize($address['shipping_city'] ?? '');
        $locality = $this->normalize($address['shipping_locality'] ?? '');

        $zones = ShippingZone::query()
            ->where('province', $address['shipping_region'])
            ->where('is_active', true)
            ->get()
            ->filter(function (ShippingZone $zone) use ($municipality, $locality): bool {
                if ($zone->municipality !== null && $this->normalize($zone->municipality) !== $municipality) {
                    return false;
                }

                if ($zone->locality !== null && $this->normalize($zone->locality) !== $locality) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (ShippingZone $zone): int => (int) ($zone->municipality !== null) + (int) ($zone->locality !== null) * 2);

        $zone = $zones->first();

        if (! $zone) {
            throw ValidationException::withMessages([
                'shipping_city' => 'Todavía no tenemos una tarifa de entrega configurada para esa dirección. Contacta con Dubai Shop para consultar la disponibilidad.',
            ]);
        }

        if ($zone->currency !== $currency) {
            throw ValidationException::withMessages([
                'shipping_city' => 'La moneda de la tarifa de entrega no coincide con la moneda de tu carrito. Contacta con Dubai Shop.',
            ]);
        }

        return $zone;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
