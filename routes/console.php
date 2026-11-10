<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('social-publicator:dispatch-outbox')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('services.social_publicator.enabled')
        && filled(config('services.social_publicator.webhook_url'))
        && filled(config('services.social_publicator.webhook_key_id'))
        && filled(config('services.social_publicator.webhook_secret')));
