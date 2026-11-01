<?php

namespace App\Payments;

use App\Contracts\Payments\PaymentDriver;
use InvalidArgumentException;

class PaymentDriverManager
{
    /** @param array<string, class-string<PaymentDriver>> $drivers */
    public function __construct(private readonly array $drivers) {}

    public function driver(string $name): PaymentDriver
    {
        $class = $this->drivers[$name] ?? null;

        if (! $class || ! is_subclass_of($class, PaymentDriver::class)) {
            throw new InvalidArgumentException("El método de pago [{$name}] no tiene un controlador válido.");
        }

        return app($class);
    }
}
