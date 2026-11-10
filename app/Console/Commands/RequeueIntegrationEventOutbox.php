<?php

namespace App\Console\Commands;

use App\Models\IntegrationEventOutbox;
use Illuminate\Console\Command;

class RequeueIntegrationEventOutbox extends Command
{
    protected $signature = 'social-publicator:requeue-outbox {eventId}';

    protected $description = 'Requeues a terminally failed SocialPublicator integration event';

    public function handle(): int
    {
        $event = IntegrationEventOutbox::query()
            ->where('event_id', $this->argument('eventId'))
            ->first();

        if (! $event) {
            $this->error('No existe el evento indicado.');

            return self::FAILURE;
        }

        if ($event->delivered_at) {
            $this->error('El evento ya fue entregado y no puede volver a encolarse.');

            return self::FAILURE;
        }

        $event->update([
            'attempts' => 0,
            'available_at' => now(),
            'locked_at' => null,
            'failed_at' => null,
            'last_error' => null,
        ]);

        $this->info('Evento reencolado: '.$event->event_id);

        return self::SUCCESS;
    }
}
