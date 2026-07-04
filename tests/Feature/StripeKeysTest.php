<?php

use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Payments\StripeKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

it('collects webhook secret candidates from env and all connections', function () {
    Connection::query()->create([
        'name' => 'Site', 'webhook_url' => 'https://s.test/w', 'webhook_secret' => 'ss',
        'stripe_live_webhook_secret' => 'whsec_own_live',
        'stripe_test_webhook_secret' => 'whsec_own_test',
    ]);

    expect(StripeKeys::webhookSecretCandidates())
        ->toBe(['whsec_live_env', 'whsec_test_env', 'whsec_own_live', 'whsec_own_test']);
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
