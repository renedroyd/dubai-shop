<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = [
        'province', 'municipality', 'locality', 'shipping_amount', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return implode(' · ', array_filter([$this->locality, $this->municipality, $this->province]));
    }
}
