<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PosPriceRule extends Model
{
    public const SCOPE_PRODUCT = 'product';
    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_BRAND = 'brand';
    public const SCOPE_ORDER = 'order';

    public const TYPE_PERCENTAGE = 'percentage_discount';
    public const TYPE_FIXED = 'fixed_discount';
    public const TYPE_SPECIAL_PRICE = 'special_price';

    protected $fillable = [
        'name', 'scope', 'target_id', 'customer_id', 'warehouse_id', 'currency', 'rule_type',
        'value_amount', 'priority', 'requires_approval', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'value_amount' => 'integer', 'priority' => 'integer',
            'requires_approval' => 'boolean', 'is_active' => 'boolean',
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PosPriceRule $rule): void {
            if (! in_array($rule->scope, [self::SCOPE_PRODUCT, self::SCOPE_CATEGORY, self::SCOPE_BRAND, self::SCOPE_ORDER], true)) {
                throw ValidationException::withMessages(['scope' => 'El alcance de la regla no es válido.']);
            }
            if (! in_array($rule->rule_type, [self::TYPE_PERCENTAGE, self::TYPE_FIXED, self::TYPE_SPECIAL_PRICE], true)) {
                throw ValidationException::withMessages(['rule_type' => 'El tipo de regla no es válido.']);
            }
            if ($rule->scope === self::SCOPE_ORDER && $rule->rule_type === self::TYPE_SPECIAL_PRICE) {
                throw ValidationException::withMessages(['rule_type' => 'El precio especial debe aplicarse a un producto, categoría o marca.']);
            }
            if ($rule->scope !== self::SCOPE_ORDER && ! $rule->target_id) {
                throw ValidationException::withMessages(['target_id' => 'Selecciona el producto, categoría o marca de la regla.']);
            }
            if ($rule->scope === self::SCOPE_ORDER) $rule->target_id = null;
            if ((int) $rule->value_amount < 1 || ($rule->rule_type === self::TYPE_PERCENTAGE && (int) $rule->value_amount > 10000)) {
                throw ValidationException::withMessages(['value_amount' => 'El valor de la regla debe ser positivo y los porcentajes no pueden superar el 100 %.']);
            }
            if (in_array($rule->rule_type, [self::TYPE_FIXED, self::TYPE_SPECIAL_PRICE], true) && ! in_array($rule->currency, ['CUP', 'USD', 'EUR'], true)) {
                throw ValidationException::withMessages(['currency' => 'Selecciona una moneda válida para los importes fijos.']);
            }
            if ($rule->starts_at && $rule->ends_at && $rule->ends_at->lt($rule->starts_at)) {
                throw ValidationException::withMessages(['ends_at' => 'La fecha final no puede ser anterior a la inicial.']);
            }
        });
    }

    public function scopeActiveAt(Builder $query, ?\Illuminate\Support\Carbon $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where(fn (Builder $rules) => $rules->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $rules) => $rules->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
