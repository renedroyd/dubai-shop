<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\ShippingZone;
use App\Models\StorefrontSetting;
use Illuminate\Database\Seeder;

class ClassicDubaiOperationalSeeder extends Seeder
{
    public function run(): void
    {
        StorefrontSetting::query()->updateOrCreate(
            ['key' => 'site_name'],
            [
                'group' => 'general',
                'label' => 'Nombre de la tienda',
                'value' => 'Clásico Dubai',
                'is_public' => true,
                'sort_order' => 1,
            ],
        );

        $zelle = PaymentMethod::query()->firstOrNew(['code' => 'zelle']);
        $zelle->fill([
            'name' => 'Zelle',
            'driver' => 'zelle_manual',
            'description' => 'Transferencia Zelle con revisión manual del comprobante.',
            'instructions' => 'Realiza el pago en USD y adjunta el comprobante para su validación.',
            'sort_order' => 1,
        ]);

        $recipientName = trim((string) env('ZELLE_RECIPIENT_NAME', ''));
        $recipientEmail = trim((string) env('ZELLE_RECIPIENT_EMAIL', ''));

        if ($recipientName !== '' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $zelle->settings = [
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
            ];
            $zelle->is_enabled = true;
        } elseif (! $zelle->exists) {
            $zelle->settings = [];
            $zelle->is_enabled = false;
        }

        $zelle->save();

        PaymentMethod::query()->updateOrCreate(
            ['code' => 'cash-on-delivery'],
            [
                'name' => 'Efectivo contra entrega',
                'driver' => 'cash_on_delivery',
                'description' => 'Pago en efectivo al recibir el pedido.',
                'instructions' => 'Prepara el importe exacto para realizar el pago al recibir tu pedido.',
                'settings' => [],
                'is_enabled' => true,
                'sort_order' => 2,
            ],
        );

        foreach ([
            'Baracoa',
            'Caimanera',
            'El Salvador',
            'Guantánamo',
            'Imías',
            'Maisí',
            'Manuel Tames',
            'Niceto Pérez',
            'San Antonio del Sur',
            'Yateras',
        ] as $municipality) {
            ShippingZone::query()->updateOrCreate(
                [
                    'province' => 'Guantánamo',
                    'municipality' => $municipality,
                    'locality' => null,
                ],
                [
                    'shipping_amount' => 500,
                    'currency' => 'CUP',
                    'is_active' => true,
                ],
            );
        }
    }
}
