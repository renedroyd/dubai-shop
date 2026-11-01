<?php

namespace App\Payments;

use App\Contracts\Payments\PaymentDriver;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentDriverManager
{
    /** @param array<string, class-string<PaymentDriver>> $drivers
     *  @param array<string, string> $labels
     */
    public function __construct(
        private readonly array $drivers,
        private readonly array $labels = [],
    ) {}

    public function driver(string $name): PaymentDriver
    {
        $class = $this->drivers[$name] ?? null;

        if (! $class || ! is_subclass_of($class, PaymentDriver::class)) {
            throw new InvalidArgumentException("El método de pago [{$name}] no tiene un controlador válido.");
        }

        return app($class);
    }

    /** @return array<string, string> */
    public function options(): array
    {
        $options = [];

        foreach ($this->drivers as $name => $class) {
            if (! is_subclass_of($class, PaymentDriver::class)) {
                continue;
            }

            $options[$name] = $this->labels[$name] ?? Str::headline($name);
        }

        return $options;
    }
}
