<?php

namespace App\Jobs;

use App\Mail\OrderNotificationMail;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $orderId,
        public readonly string $eventKey,
    ) {}

    public function handle(): void
    {
        $order = Order::query()->with(['items', 'shipment'])->find($this->orderId);

        if (! $order || ! filled($order->customer_email)) {
            return;
        }

        $recipient = trim($order->customer_email);
        $template = NotificationTemplate::query()->where('event_key', $this->eventKey)->first();
        $log = NotificationLog::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'event_key' => $this->eventKey,
                'recipient' => $recipient,
            ],
            [
                'notification_template_id' => $template?->id,
                'subject' => $template?->subject,
                'status' => $template?->is_active ? 'queued' : 'skipped',
                'error_message' => $template ? null : 'No existe una plantilla para este evento.',
            ],
        );

        if ($log->status === 'sent' || $log->status === 'skipped') {
            return;
        }

        if (! $template || ! $template->is_active) {
            $log->update([
                'notification_template_id' => $template?->id,
                'status' => 'skipped',
                'error_message' => $template ? 'La plantilla está desactivada.' : 'No existe una plantilla para este evento.',
            ]);

            return;
        }

        $variables = [
            '{order_number}' => $order->number,
            '{customer_name}' => $order->customer_name,
            '{order_status}' => $order->status,
            '{payment_status}' => $order->payment_status,
            '{order_total}' => number_format($order->total_amount / 100, 2, '.', ''),
            '{currency}' => $order->currency,
            '{store_name}' => config('app.name', 'Dubai Shop'),
            '{tracking_code}' => $order->shipment?->tracking_code ?? '',
            '{courier_name}' => $order->shipment?->courier_name ?? '',
            '{courier_phone}' => $order->shipment?->courier_phone ?? '',
        ];
        $subject = strtr($template->subject, $variables);
        $body = strtr($template->body, $variables);

        $log->update([
            'notification_template_id' => $template->id,
            'subject' => $subject,
            'status' => 'sending',
            'attempts' => $log->attempts + 1,
            'error_message' => null,
        ]);

        try {
            Mail::to($recipient)->send(new OrderNotificationMail($subject, $body));
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 4000),
            ]);

            throw $exception;
        }

        $log->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        NotificationLog::query()
            ->where('order_id', $this->orderId)
            ->where('event_key', $this->eventKey)
            ->update([
                'status' => 'failed',
                'error_message' => $exception ? Str::limit($exception->getMessage(), 4000) : 'El envío agotó los reintentos.',
            ]);
    }
}
