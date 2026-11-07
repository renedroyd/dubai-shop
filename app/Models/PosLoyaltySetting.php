<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosLoyaltySetting extends Model
{
    protected $fillable = ['currency', 'earning_amount', 'points_per_earning_unit', 'redemption_amount', 'minimum_redemption_points', 'maximum_redemption_basis_points', 'expiration_months', 'is_enabled'];

    protected function casts(): array
    {
        return ['earning_amount' => 'integer', 'points_per_earning_unit' => 'integer', 'redemption_amount' => 'integer', 'minimum_redemption_points' => 'integer', 'maximum_redemption_basis_points' => 'integer', 'expiration_months' => 'integer', 'is_enabled' => 'boolean'];
    }
}
