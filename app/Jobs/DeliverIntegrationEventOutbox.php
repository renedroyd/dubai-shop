<?php

namespace App\Jobs;

use App\Models\IntegrationEventOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DeliverIntegrationEventOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $outboxId) {}

    public function handle(): void
    {
        $event = IntegrationEventOutbox::query()->findOrFail($this->outboxId);

        if ($event->delivered_at || $event->failed_at) {
            return;
        }

        try {
            $url = (string) config('services.social_publicator.webhook_url');
            $keyId = (string) config('services.social_publicator.webhook_key_id');
            $secret = (string) config('services.social_publicator.webhook_secret');

            if ($url === '' || $keyId === '' || $secret === '') {
                throw new RuntimeException('SocialPublicator webhook URL, key ID and secret must be configured.');
            }

            if (! app()->environment('local') && parse_url($url, PHP_URL_SCHEME) !== 'https') {
                throw new RuntimeException('SocialPublicator webhook URL must use HTTPS outside local development.');
            }

            $body = json_encode(
                $event->payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
            $timestamp = (string) now()->timestamp;
            $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

            $response = Http::timeout((int) config('services.social_publicator.timeout', 15))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-DubaiShop-Key-Id' => $keyId,
                    'X-DubaiShop-Timestamp' => $timestamp,
                    'X-DubaiShop-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if (! $response->successful()) {
                throw new RuntimeException('SocialPublicator webhook returned HTTP '.$response->status().'.');
            }

            $event->update([
                'delivered_at' => now(),
                'available_at' => null,
                'locked_at' => null,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $attempts = $event->attempts + 1;
            $delays = [30, 120, 300, 600, 1800, 3600, 7200];
            $isTerminal = $attempts >= 8;

            $event->update([
                'attempts' => $attempts,
                'available_at' => $isTerminal ? null : now()->addSeconds($delays[$attempts - 1]),
                'locked_at' => null,
                'failed_at' => $isTerminal ? now() : null,
                'last_error' => Str::limit($exception->getMessage(), 4000),
            ]);

            Log::warning('Dubai Shop integration event delivery failed.', [
                'outbox_id' => $event->id,
                'event_id' => $event->event_id,
                'attempts' => $attempts,
                'terminal' => $isTerminal,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
