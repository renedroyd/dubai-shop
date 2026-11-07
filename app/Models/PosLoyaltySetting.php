<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosLoyaltySetting extends Model
{
    protected $fillable = ['currency', 'earning_amount', 'points_per_earning_unit', 'expiration_months', 'is_enabled'];

    protected function casts(): array
    {
        return ['earning_amount' => 'integer', 'points_per_earning_unit' => 'integer', 'expiration_months' => 'integer', 'is_enabled' => 'boolean'];
    }
}
