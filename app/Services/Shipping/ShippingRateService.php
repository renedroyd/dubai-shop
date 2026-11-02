<?php

namespace App\Services\Shipping;

use App\Models\ShippingZone;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShippingRateService
{
    /** @return array<string, string> */
    public static function provinces(): array
    {
        return [
            'Guantánamo' => 'Guantánamo',
        ];
    }

    public function resolve(array $address, string $currency): ShippingZone
    {
        if (($address['shipping_country'] ?? null) !== 'CU') {
            throw ValidationException::withMessages([
                'shipping_country' => 'Dubai Shop realiza entregas únicamente dentro de Cuba.',
            ]);
        }

        if (($address['shipping_region'] ?? null) !== 'Guantánamo') {
            throw ValidationException::withMessages([
                'shipping_region' => 'Por el momento Dubai Shop realiza entregas únicamente en la provincia de Guantánamo.',
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

    /**
     * Normalize user-entered Cuban municipality/locality names so accents,
     * casing and repeated whitespace do not prevent a valid rate from matching.
     */
    private function normalize(string $value): string
    {
        $ascii = Str::ascii(trim($value));

        return Str::lower((string) preg_replace('/\s+/', ' ', $ascii));
    }
}
