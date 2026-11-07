<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostChange extends Model
{
    protected $fillable = [
        'inventory_id', 'user_id', 'previous_acquisition_cost_amount', 'new_acquisition_cost_amount',
        'previous_minimum_sale_price_amount', 'new_minimum_sale_price_amount', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_acquisition_cost_amount' => 'integer',
            'new_acquisition_cost_amount' => 'integer',
            'previous_minimum_sale_price_amount' => 'integer',
            'new_minimum_sale_price_amount' => 'integer',
        ];
    }

    public function inventory(): BelongsTo { return $this->belongsTo(Inventory::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
