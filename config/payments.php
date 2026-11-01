<?php

use App\Payments\Drivers\CashOnDeliveryDriver;
use App\Payments\Drivers\ZelleManualDriver;

return [
    'drivers' => [
        'zelle_manual' => ZelleManualDriver::class,
        'cash_on_delivery' => CashOnDeliveryDriver::class,
    ],

    'labels' => [
        'zelle_manual' => 'Zelle — validación manual',
        'cash_on_delivery' => 'Efectivo contra entrega',
    ],
];
