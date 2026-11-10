<?php

namespace App\Console\Commands;

use App\Jobs\DeliverIntegrationEventOutbox;
use App\Models\IntegrationEventOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchIntegrationEventOutbox extends Command
{
    protected $signature = 'social-publicator:dispatch-outbox {--limit=100}';

    protected $description = 'Dispatches pending Dubai Shop integration events to SocialPublicator';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));

        $ids = DB::transaction(function () use ($limit): array {
            $events = IntegrationEventOutbox::query()
                ->whereNull('delivered_at')
                ->whereNull('failed_at')
                ->whereNotNull('available_at')
                ->where('available_at', '<=', now())
                ->where(fn ($query) => $query
                    ->whereNull('locked_at')
                    ->orWhere('locked_at', '<=', now()->subMinutes(10)))
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($events as $event) {
                $event->update(['locked_at' => now()]);
            }

            return $events->modelKeys();
        });

        foreach ($ids as $id) {
            DeliverIntegrationEventOutbox::dispatch($id);
        }

        $this->info('Eventos enviados a cola: '.count($ids));

        return self::SUCCESS;
    }
}
