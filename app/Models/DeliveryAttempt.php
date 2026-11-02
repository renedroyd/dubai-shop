<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    protected $fillable = [
        'shipment_id', 'attempt_number', 'result', 'recipient_name', 'note',
        'recorded_by', 'attempted_at',
    ];

    protected function casts(): array
    {
        return ['attempt_number' => 'integer', 'attempted_at' => 'datetime'];
    }

    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
