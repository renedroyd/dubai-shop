<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PosLoyaltySetting extends Model
{
    protected $fillable = ['currency', 'earning_amount', 'points_per_earning_unit', 'redemption_amount', 'minimum_redemption_points', 'maximum_redemption_basis_points', 'expiration_months', 'is_enabled'];

    protected function casts(): array
    {
        return ['earning_amount' => 'integer', 'points_per_earning_unit' => 'integer', 'redemption_amount' => 'integer', 'minimum_redemption_points' => 'integer', 'maximum_redemption_basis_points' => 'integer', 'expiration_months' => 'integer', 'is_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (PosLoyaltySetting $setting): void {
            if (! in_array($setting->currency, ['CUP', 'USD', 'EUR'], true)) {
                throw ValidationException::withMessages(['currency' => 'La moneda de fidelización no es válida.']);
            }
            foreach (['earning_amount', 'points_per_earning_unit', 'redemption_amount', 'minimum_redemption_points', 'expiration_months'] as $field) {
                if ((int) $setting->{$field} < 1) {
                    throw ValidationException::withMessages([$field => 'El valor debe ser mayor que cero.']);
                }
            }
            if ((int) $setting->maximum_redemption_basis_points < 1 || (int) $setting->maximum_redemption_basis_points > 10000) {
                throw ValidationException::withMessages(['maximum_redemption_basis_points' => 'El límite de canje debe estar entre 0.01 % y 100 %.']);
            }
        });
    }

}
