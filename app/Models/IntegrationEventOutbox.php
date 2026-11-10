<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEventOutbox extends Model
{
    protected $table = 'integration_event_outbox';

    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_external_id',
        'revision',
        'payload',
        'occurred_at',
        'attempts',
        'available_at',
        'delivered_at',
        'failed_at',
        'locked_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
