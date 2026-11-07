<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'value_amount' => 'integer',
            'priority' => 'integer',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
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
