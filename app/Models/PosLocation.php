<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class PosLocation extends Model
{
    protected $fillable = ['code', 'name', 'address', 'phone', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $location): void {
            if ($location->isDirty('is_active') && ! $location->is_active
                && $location->terminals()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'is_active' => __('Desactiva primero los terminales activos de este establecimiento.'),
                ]);
            }
        });
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class);
    }
}
