<?php

use App\Http\Controllers\Api\Integrations\SocialPublicatorCatalogController;
use App\Http\Middleware\AuthenticateIntegrationApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations/social-publicator/v1')
    ->middleware([
        'throttle:60,1',
        AuthenticateIntegrationApiToken::class.':catalog:read',
    ])
    ->group(function (): void {
        Route::get('/items', [SocialPublicatorCatalogController::class, 'items']);
        Route::get('/items/{externalId}', [SocialPublicatorCatalogController::class, 'show'])
            ->whereUuid('externalId');
        Route::get('/warehouses', [SocialPublicatorCatalogController::class, 'warehouses']);
    });
