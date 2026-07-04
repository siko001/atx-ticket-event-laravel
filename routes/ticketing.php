<?php

use AtxDigital\Ticketing\Http\Controllers\CheckInController;
use AtxDigital\Ticketing\Http\Controllers\CheckInStatsController;
use AtxDigital\Ticketing\Http\Controllers\CheckoutController;
use AtxDigital\Ticketing\Http\Controllers\DevPreviewController;
use AtxDigital\Ticketing\Http\Controllers\StripeWebhookController;
use AtxDigital\Ticketing\Http\Controllers\WordPressConnectController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('ticketing.routes.api_prefix', 'api/ticketing'))
    ->middleware((array) config('ticketing.routes.api_middleware', ['api']))
    ->name('ticketing.')
    ->group(function () {
        Route::post('events/{event}/checkout', CheckoutController::class)
            ->middleware('throttle:ticketing-checkout')
            ->whereNumber('event')
            ->name('checkout');

        Route::post('stripe/webhook', StripeWebhookController::class)
            ->name('stripe.webhook');

        // Called by the WordPress plugin (HMAC-signed with the shared secret).
        Route::get('wp/ping', [WordPressConnectController::class, 'ping'])
            ->name('wp.ping');
        Route::get('wp/events', [WordPressConnectController::class, 'events'])
            ->name('wp.events');
        Route::post('wp/mode', [WordPressConnectController::class, 'mode'])
            ->name('wp.mode');
    });

if (config('ticketing.features.check_in', true)) {
    Route::prefix('ticketing/checkin')
        ->middleware([
            ...(array) config('ticketing.checkin.middleware', ['web', 'auth']),
            'can:ticketing.checkin',
        ])
        ->name('ticketing.checkin.')
        ->group(function () {
            Route::get('occurrences/{occurrence}/stats', CheckInStatsController::class)
                ->whereNumber('occurrence')
                ->name('stats');

            Route::post('{token}', CheckInController::class)
                ->where('token', '[A-Za-z0-9]+')
                ->name('scan');
        });
}

if (config('ticketing.previews.enabled') ?? app()->environment('local')) {
    Route::prefix('ticketing/dev')
        ->middleware((array) config('ticketing.previews.middleware', ['web', 'auth']))
        ->name('ticketing.dev.')
        ->group(function () {
            Route::get('/', [DevPreviewController::class, 'index'])->name('index');
            Route::get('mail/order-confirmation', [DevPreviewController::class, 'mail'])->name('mail');
            Route::get('pdf/ticket', [DevPreviewController::class, 'ticket'])->name('ticket');
            Route::get('ics/event', [DevPreviewController::class, 'ics'])->name('ics');
        });
}
