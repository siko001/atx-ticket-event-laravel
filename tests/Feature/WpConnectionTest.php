<?php

use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Support\Settings;
use AtxDigital\Ticketing\Support\WpConnection;
use AtxDigital\Ticketing\WordPress\ConnectionTester;
use AtxDigital\Ticketing\WordPress\WebhookDispatcher;
use Illuminate\Support\Facades\Http;

it('falls back to env config when no connections exist', function () {
    config()->set('ticketing.wp_webhook_url', 'https://env.test/webhook');
    config()->set('ticketing.wp_webhook_secret', 'env-secret');

    $targets = WpConnection::targets();

    expect($targets)->toHaveCount(1)
        ->and($targets->first()->webhook_url)->toBe('https://env.test/webhook')
        ->and($targets->first()->exists)->toBeFalse()
        ->and(WpConnection::secrets())->toBe(['env-secret']);
});

it('uses connection rows over the env fallback and skips inactive ones', function () {
    config()->set('ticketing.wp_webhook_url', 'https://env.test/webhook');
    config()->set('ticketing.wp_webhook_secret', 'env-secret');

    Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a']);
    Connection::query()->create(['name' => 'Site B', 'webhook_url' => 'https://b.test/webhook', 'webhook_secret' => 'secret-b']);
    Connection::query()->create(['name' => 'Old site', 'webhook_url' => 'https://old.test/webhook', 'webhook_secret' => 'secret-old', 'is_active' => false]);

    expect(WpConnection::targets()->pluck('name')->all())->toBe(['Site A', 'Site B'])
        ->and(WpConnection::secrets())->toBe(['secret-a', 'secret-b']);
});

it('matches the signing connection by secret', function () {
    Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a']);
    Connection::query()->create(['name' => 'Site B', 'webhook_url' => 'https://b.test/webhook', 'webhook_secret' => 'secret-b']);

    $timestamp = (string) time();
    $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.', 'secret-b');

    expect(WpConnection::matchSecret($timestamp, $signature, '')?->name)->toBe('Site B')
        ->and(WpConnection::matchSecret($timestamp, 'sha256=bad', ''))->toBeNull();
});

it('pushes webhooks to every active connection with its own secret', function () {
    Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a']);
    Connection::query()->create(['name' => 'Site B', 'webhook_url' => 'https://b.test/webhook', 'webhook_secret' => 'secret-b']);

    Http::fake(['*' => Http::response(['ok' => true])]);

    app(WebhookDispatcher::class)->send('event.updated', ['id' => 1]);

    foreach ([['a.test', 'secret-a'], ['b.test', 'secret-b']] as [$host, $secret]) {
        Http::assertSent(function ($request) use ($host, $secret) {
            if (! str_contains($request->url(), $host)) {
                return false;
            }

            $timestamp = $request->header('X-Atx-Ticketing-Timestamp')[0] ?? '';

            return ($request->header('X-Atx-Ticketing-Signature')[0] ?? '')
                === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret);
        });
    }
});

it('can push to a single connection only', function () {
    $siteA = Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a']);
    Connection::query()->create(['name' => 'Site B', 'webhook_url' => 'https://b.test/webhook', 'webhook_secret' => 'secret-b']);

    Http::fake(['*' => Http::response(['ok' => true])]);

    app(WebhookDispatcher::class)->send('event.updated', ['id' => 1], (int) $siteA->getKey());

    Http::assertSent(fn ($request) => str_contains($request->url(), 'a.test'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'b.test'));
});

it('tests a connection and records the result on its row', function () {
    $connection = Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/wp-json/atx-ticketing/v1/webhook', 'webhook_secret' => 'secret-a']);

    Http::fake(['a.test/*' => Http::response(['ignored' => 'connection.test'])]);

    $result = app(ConnectionTester::class)->test($connection);

    expect($result['ok'])->toBeTrue()
        ->and($connection->refresh()->last_test['ok'])->toBeTrue();
});

it('detects a secret mismatch when testing', function () {
    $connection = Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'wrong']);

    Http::fake(['a.test/*' => Http::response(['error' => 'Invalid signature.'], 401)]);

    $result = app(ConnectionTester::class)->test($connection);

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('secret');
});

it('accepts pull requests signed with any active connection secret', function () {
    $siteB = Connection::query()->create(['name' => 'Site B', 'webhook_url' => 'https://b.test/webhook', 'webhook_secret' => 'secret-b']);

    $timestamp = (string) time();
    $headers = [
        'X-Atx-Ticketing-Timestamp' => $timestamp,
        'X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.', 'secret-b'),
        'Accept' => 'application/json',
    ];

    test()->get('/api/ticketing/wp/ping', $headers)->assertOk();

    expect($siteB->refresh()->last_pull['action'] ?? null)->toBe('ping');
});

it('rejects pulls signed with an inactive connection secret', function () {
    Connection::query()->create(['name' => 'Old', 'webhook_url' => 'https://old.test/webhook', 'webhook_secret' => 'secret-old', 'is_active' => false]);
    Connection::query()->create(['name' => 'Live', 'webhook_url' => 'https://live.test/webhook', 'webhook_secret' => 'secret-live']);

    $timestamp = (string) time();

    test()->get('/api/ticketing/wp/ping', [
        'X-Atx-Ticketing-Timestamp' => $timestamp,
        'X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.', 'secret-old'),
        'Accept' => 'application/json',
    ])->assertStatus(401);
});

it('round-trips arbitrary setting values', function () {
    Settings::set('wp.last_test', ['ok' => true, 'message' => 'fine']);

    expect(Settings::get('wp.last_test'))->toBe(['ok' => true, 'message' => 'fine'])
        ->and(Settings::get('missing', 'fallback'))->toBe('fallback');
});

it('lets WordPress switch its connection to test mode via wp/mode', function () {
    Http::fake();
    $site = Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a']);

    $body = json_encode(['test_mode' => true]);
    $timestamp = (string) time();

    test()->call('POST', '/api/ticketing/wp/mode', [], [], [], [
        'HTTP_X-Atx-Ticketing-Timestamp' => $timestamp,
        'HTTP_X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'secret-a'),
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($site->refresh()->is_test_mode)->toBeTrue();

    // saveQuietly: no echo webhook back to the site.
    Http::assertNothingSent();
});

it('pushes a connection.mode webhook when test mode is toggled in the admin', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);
    $site = Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a']);

    $site->update(['is_test_mode' => true]);

    Http::assertSent(function ($request) {
        $payload = json_decode($request->body(), true);

        return str_contains($request->url(), 'a.test')
            && $payload['type'] === 'connection.mode'
            && $payload['event']['test_mode'] === true;
    });
});

it('reports test mode in the ping response', function () {
    Connection::query()->create(['name' => 'Site A', 'webhook_url' => 'https://a.test/webhook', 'webhook_secret' => 'secret-a', 'is_test_mode' => true]);

    $timestamp = (string) time();

    test()->get('/api/ticketing/wp/ping', [
        'X-Atx-Ticketing-Timestamp' => $timestamp,
        'X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.', 'secret-a'),
        'Accept' => 'application/json',
    ])->assertOk()->assertJson(['test_mode' => true]);
});
