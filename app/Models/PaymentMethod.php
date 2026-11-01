<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'code', 'driver', 'description', 'instructions', 'settings', 'is_enabled', 'sort_order'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_enabled' => 'boolean', 'sort_order' => 'integer'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
