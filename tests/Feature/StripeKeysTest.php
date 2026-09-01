<?php

use AtxDigital\Ticketing\Contracts\ResolvesWordPressConnections;
use AtxDigital\Ticketing\Contracts\WordPressConnectionProvider;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Payments\StripeKeys;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StripeWordPressConnectionProvider implements ResolvesWordPressConnections, WordPressConnectionProvider
{
    public function targets(): Collection
    {
        return new Collection([$this->connection()]);
    }

    public function resolve(string $reference): ?Connection
    {
        return $reference === 'wordpress-site:42' ? $this->connection() : null;
    }

    private function connection(): Connection
    {
        return new Connection([
            'name' => 'Host WordPress site',
            'webhook_url' => 'https://host.test/webhook',
            'webhook_secret' => 'host-signing-secret',
            'is_active' => true,
            'is_test_mode' => true,
            'provider_reference' => 'wordpress-site:42',
            'stripe_live_secret' => 'sk_live_host',
            'stripe_live_webhook_secret' => 'whsec_live_host',
            'stripe_test_secret' => 'sk_test_host',
            'stripe_test_webhook_secret' => 'whsec_test_host',
        ]);
    }
}

class HistoricalStripeWordPressConnectionProvider implements ResolvesWordPressConnections, WordPressConnectionProvider
{
    public function targets(): Collection
    {
        return new Collection;
    }

    public function resolve(string $reference): ?Connection
    {
        if ($reference !== 'wordpress-site:42') {
            return null;
        }

        return new Connection([
            'name' => 'Inactive host WordPress site',
            'stripe_live_webhook_secret' => 'whsec_historical_live',
            'stripe_test_webhook_secret' => 'whsec_historical_test',
        ]);
    }
}

beforeEach(function () {
    fakeTicketingServices();
    Storage::fake('local');
    Http::fake(['*' => Http::response(['ok' => true])]);

    config()->set('ticketing.stripe.secret', 'sk_live_env');
    config()->set('ticketing.stripe.test_secret', 'sk_test_env');
    config()->set('ticketing.stripe.webhook_secret', 'whsec_live_env');
    config()->set('ticketing.stripe.test_webhook_secret', 'whsec_test_env');
});

function orderWith(?Connection $connection, bool $isTest): Order
{
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 1000]);

    return Order::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'connection_id' => $connection?->getKey(),
        'is_test' => $isTest,
    ]);
}

it('resolves env keys by mode when the connection has no overrides', function () {
    $connection = Connection::query()->create(['name' => 'Site', 'webhook_url' => 'https://s.test/w', 'webhook_secret' => 'ss']);

    expect(StripeKeys::secretForOrder(orderWith($connection, false)))->toBe('sk_live_env')
        ->and(StripeKeys::secretForOrder(orderWith($connection, true)))->toBe('sk_test_env')
        ->and(StripeKeys::secretForOrder(orderWith(null, false)))->toBe('sk_live_env');
});

it('prefers the connection key overrides', function () {
    $connection = Connection::query()->create([
        'name' => 'Site', 'webhook_url' => 'https://s.test/w', 'webhook_secret' => 'ss',
        'stripe_live_secret' => 'sk_live_own',
        'stripe_test_secret' => 'sk_test_own',
    ]);

    expect(StripeKeys::secretForOrder(orderWith($connection, false)))->toBe('sk_live_own')
        ->and(StripeKeys::secretForOrder(orderWith($connection, true)))->toBe('sk_test_own');
});

it('resolves host-provided keys for historical orders', function () {
    config()->set('ticketing.wordpress.connection_provider', StripeWordPressConnectionProvider::class);

    $liveOrder = orderWith(null, false);
    $liveOrder->update(['connection_reference' => 'wordpress-site:42']);
    $testOrder = orderWith(null, true);
    $testOrder->update(['connection_reference' => 'wordpress-site:42']);

    expect(StripeKeys::secretForOrder($liveOrder))->toBe('sk_live_host')
        ->and(StripeKeys::secretForOrder($testOrder))->toBe('sk_test_host');
});

it('collects webhook secret candidates from env and all connections', function () {
    Connection::query()->create([
        'name' => 'Site', 'webhook_url' => 'https://s.test/w', 'webhook_secret' => 'ss',
        'stripe_live_webhook_secret' => 'whsec_own_live',
        'stripe_test_webhook_secret' => 'whsec_own_test',
    ]);

    expect(StripeKeys::webhookSecretCandidates())
        ->toBe(['whsec_live_env', 'whsec_test_env', 'whsec_own_live', 'whsec_own_test']);
});

it('collects webhook signing secrets from a host connection provider', function () {
    config()->set('ticketing.wordpress.connection_provider', StripeWordPressConnectionProvider::class);

    expect(StripeKeys::webhookSecretCandidates())
        ->toBe(['whsec_live_env', 'whsec_test_env', 'whsec_live_host', 'whsec_test_host']);
});

it('keeps webhook signing secrets available for historical host orders', function () {
    config()->set('ticketing.wordpress.connection_provider', HistoricalStripeWordPressConnectionProvider::class);
    $order = orderWith(null, true);
    $order->update(['connection_reference' => 'wordpress-site:42']);

    expect(StripeKeys::webhookSecretCandidates())
        ->toBe(['whsec_live_env', 'whsec_test_env', 'whsec_historical_live', 'whsec_historical_test']);
});

it('stores connection stripe keys encrypted at rest', function () {
    $connection = Connection::query()->create([
        'name' => 'Site', 'webhook_url' => 'https://s.test/w', 'webhook_secret' => 'ss',
        'stripe_live_secret' => 'sk_live_own',
    ]);

    $raw = DB::table('ticketing_connections')
        ->where('id', $connection->getKey())->value('stripe_live_secret');

    expect($raw)->not->toContain('sk_live_own')
        ->and($connection->refresh()->stripe_live_secret)->toBe('sk_live_own');
});

it('marks signed WP checkouts with the connection and its mode', function () {
    $connection = Connection::query()->create([
        'name' => 'Test site', 'webhook_url' => 'https://s.test/w',
        'webhook_secret' => 'proxy-secret', 'is_test_mode' => true,
    ]);

    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 0]);

    $payload = checkoutPayload($occurrence, $ticketType);
    $body = json_encode($payload);
    $timestamp = (string) time();

    $response = test()->call('POST', checkoutUrl($event), [], [], [], [
        'HTTP_X-Atx-Ticketing-Timestamp' => $timestamp,
        'HTTP_X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'proxy-secret'),
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertCreated();

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->connection_id)->toBe($connection->getKey())
        ->and($order->is_test)->toBeTrue();
});

it('leaves unsigned checkouts on live mode with no connection', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 0]);

    $response = test()->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType));

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->connection_id)->toBeNull()
        ->and($order->is_test)->toBeFalse();
});

it('snapshots a host connection reference and test mode during checkout', function () {
    config()->set('ticketing.wordpress.connection_provider', StripeWordPressConnectionProvider::class);

    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 0]);

    $payload = checkoutPayload($occurrence, $ticketType);
    $body = json_encode($payload);
    $timestamp = (string) time();

    $response = test()->call('POST', checkoutUrl($event), [], [], [], [
        'HTTP_X-Atx-Ticketing-Timestamp' => $timestamp,
        'HTTP_X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'host-signing-secret'),
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertCreated();

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->connection_id)->toBeNull()
        ->and($order->connection_reference)->toBe('wordpress-site:42')
        ->and($order->is_test)->toBeTrue();
});
