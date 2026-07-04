<?php

namespace AtxDigital\Ticketing;

use AtxDigital\Ticketing\Console\MaterializeOccurrencesCommand;
use AtxDigital\Ticketing\Console\PushEventsToWordPressCommand;
use AtxDigital\Ticketing\Contracts\PaymentGatewayContract;
use AtxDigital\Ticketing\Contracts\PaymentVerifierContract;
use AtxDigital\Ticketing\Contracts\PdfGeneratorContract;
use AtxDigital\Ticketing\Contracts\QrCodeGeneratorContract;
use AtxDigital\Ticketing\Events\EventCancelled;
use AtxDigital\Ticketing\Events\EventDeleted;
use AtxDigital\Ticketing\Events\EventPublished;
use AtxDigital\Ticketing\Events\EventUpdated;
use AtxDigital\Ticketing\Events\OrderCreated;
use AtxDigital\Ticketing\Events\OrderPaid;
use AtxDigital\Ticketing\Events\OrderRefunded;
use AtxDigital\Ticketing\Jobs\PushEventToWordPress;
use AtxDigital\Ticketing\Listeners\DispatchWordPressSync;
use AtxDigital\Ticketing\Listeners\QueueTicketFulfillment;
use AtxDigital\Ticketing\Listeners\RecordOrderActivity;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Observers\EventObserver;
use AtxDigital\Ticketing\Payments\StripeGateway;
use AtxDigital\Ticketing\Pricing\PricingEngine;
use AtxDigital\Ticketing\Registration\RegistrationFormBuilder;
use AtxDigital\Ticketing\Services\DompdfGenerator;
use AtxDigital\Ticketing\Services\EndroidQrCodeGenerator;
use AtxDigital\Ticketing\Support\ActivityLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Stripe\StripeClient;

class TicketingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('ticketing')
            ->hasConfigFile()
            ->hasViews('ticketing')
            ->hasMigrations(['create_ticketing_tables', 'create_ticketing_settings_table', 'create_ticketing_connections_table', 'update_ticketing_connections_add_test_mode', 'create_ticketing_logs_table', 'update_ticketing_events_add_media', 'update_ticketing_events_add_attendee_details'])
            ->hasCommands([
                MaterializeOccurrencesCommand::class,
                PushEventsToWordPressCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            $secret = (string) config('ticketing.stripe.secret');

            // A placeholder keeps the container resolvable when Stripe isn't
            // configured yet (e.g. free events only); the gateway refuses to
            // make real calls without a key — see StripeGateway::assertConfigured().
            return new StripeClient($secret !== '' ? $secret : 'sk_test_not_configured');
        });

        $this->app->singleton(PaymentGatewayContract::class, StripeGateway::class);
        $this->app->singleton(PaymentVerifierContract::class, StripeGateway::class);
        $this->app->singleton(QrCodeGeneratorContract::class, EndroidQrCodeGenerator::class);
        $this->app->singleton(PdfGeneratorContract::class, DompdfGenerator::class);

        $this->app->singleton(PricingEngine::class, function () {
            return new PricingEngine((array) config('ticketing.pricing_rules', []));
        });

        $this->app->singleton(RegistrationFormBuilder::class);
    }

    public function packageBooted(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/ticketing.php');

        ticketing_model('event')::observe(EventObserver::class);

        EventFacade::listen(EventPublished::class, DispatchWordPressSync::class);
        EventFacade::listen(EventUpdated::class, DispatchWordPressSync::class);
        EventFacade::listen(EventCancelled::class, DispatchWordPressSync::class);
        EventFacade::listen(EventDeleted::class, DispatchWordPressSync::class);
        EventFacade::listen(OrderPaid::class, QueueTicketFulfillment::class);
        EventFacade::listen(OrderCreated::class, RecordOrderActivity::class);
        EventFacade::listen(OrderPaid::class, RecordOrderActivity::class);
        EventFacade::listen(OrderRefunded::class, RecordOrderActivity::class);

        // Test-mode toggles sync to the affected WordPress site instantly.
        // (saveQuietly on the WP-initiated path prevents an echo loop.)
        Connection::updated(function (Connection $connection): void {
            if ($connection->wasChanged('is_test_mode')) {
                ActivityLogger::sync(
                    "Connection \"{$connection->name}\" switched to ".($connection->is_test_mode ? 'TEST' : 'live').' mode.',
                    ['connection' => $connection->name, 'test_mode' => $connection->is_test_mode],
                    $connection->is_test_mode ? 'warning' : 'info',
                );

                PushEventToWordPress::dispatch(
                    'connection.mode',
                    ['test_mode' => (bool) $connection->is_test_mode],
                    (int) $connection->getKey(),
                );
            }
        });

        // Host apps scope check-in access by defining their own
        // "ticketing.checkin" gate before this provider boots; this fallback
        // defers to a canAccessTicketingCheckIn() method when the user model
        // has one.
        if (! Gate::has('ticketing.checkin')) {
            Gate::define('ticketing.checkin', function ($user) {
                return method_exists($user, 'canAccessTicketingCheckIn')
                    ? (bool) $user->canAccessTicketingCheckIn()
                    : true;
            });
        }

        RateLimiter::for('ticketing-checkout', function (Request $request) {
            return Limit::perMinute((int) config('ticketing.checkout.rate_limit_per_minute', 20))
                ->by((string) $request->ip());
        });

        if (config('ticketing.recurrence.schedule', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('ticketing:materialize-occurrences')->daily();
            });
        }
    }
}
