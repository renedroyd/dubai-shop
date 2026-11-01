<?php

use App\Payments\Drivers\CashOnDeliveryDriver;
use App\Payments\Drivers\ZelleManualDriver;

return [
    'drivers' => [
        'zelle_manual' => ZelleManualDriver::class,
        'cash_on_delivery' => CashOnDeliveryDriver::class,
    ],
];
