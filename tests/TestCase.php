<?php

namespace AtxDigital\Ticketing\Tests;

use AtxDigital\Ticketing\Tests\Support\TestUser;
use AtxDigital\Ticketing\TicketingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TicketingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('app.url', 'https://ticketing.test');

        $app['config']->set('ticketing.stripe.secret', 'sk_test_fake');
        $app['config']->set('ticketing.stripe.webhook_secret', 'whsec_test');
        $app['config']->set('ticketing.checkout.success_url', 'https://shop.test/thanks');
        $app['config']->set('ticketing.checkout.cancel_url', 'https://shop.test/cancelled');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
