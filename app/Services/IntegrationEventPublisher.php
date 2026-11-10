<?php

namespace App\Services;

use App\Models\IntegrationEventOutbox;
use Illuminate\Support\Str;

class IntegrationEventPublisher
{
    public function publish(
        string $eventType,
        string $aggregateType,
        string $aggregateExternalId,
        int $revision,
        array $data = [],
    ): IntegrationEventOutbox {
        $eventId = (string) Str::uuid();
        $occurredAt = now();

        $payload = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'contract_version' => 'v1',
            'aggregate_external_id' => $aggregateExternalId,
            'revision' => max(1, $revision),
            'occurred_at' => $occurredAt->toISOString(),
            'data' => array_merge(['item_external_id' => $aggregateExternalId], $data),
        ];

        return IntegrationEventOutbox::query()->create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_external_id' => $aggregateExternalId,
            'revision' => max(1, $revision),
            'payload' => $payload,
            'occurred_at' => $occurredAt,
            'available_at' => $occurredAt,
        ]);
    }
}
