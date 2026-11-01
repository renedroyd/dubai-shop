<?php

namespace Tests\Unit;

use App\Payments\Drivers\CashOnDeliveryDriver;
use App\Payments\Drivers\ZelleManualDriver;
use App\Payments\PaymentDriverManager;
use PHPUnit\Framework\TestCase;

class PaymentDriverManagerTest extends TestCase
{
    public function test_registered_drivers_are_exposed_as_filament_options_with_configured_labels(): void
    {
        $manager = new PaymentDriverManager(
            [
                'zelle_manual' => ZelleManualDriver::class,
                'cash_on_delivery' => CashOnDeliveryDriver::class,
            ],
            [
                'zelle_manual' => 'Zelle — validación manual',
                'cash_on_delivery' => 'Efectivo contra entrega',
            ],
        );

        $this->assertSame([
            'zelle_manual' => 'Zelle — validación manual',
            'cash_on_delivery' => 'Efectivo contra entrega',
        ], $manager->options());
    }

    public function test_unlabeled_registered_driver_gets_a_human_readable_fallback_label(): void
    {
        $manager = new PaymentDriverManager([
            'example_gateway' => ZelleManualDriver::class,
        ]);

        $this->assertSame(['example_gateway' => 'Example Gateway'], $manager->options());
    }

    public function test_invalid_driver_classes_are_not_offered_as_payment_options(): void
    {
        $manager = new PaymentDriverManager([
            'invalid' => \stdClass::class,
        ]);

        $this->assertSame([], $manager->options());
    }
}
