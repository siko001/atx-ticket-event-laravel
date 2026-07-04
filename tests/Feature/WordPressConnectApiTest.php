<?php

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Models\TicketType;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('ticketing.wp_webhook_secret', 'shared-secret');
});

/**
 * @return array<string, string>
 */
function wpSignedHeaders(string $secret = 'shared-secret', ?int $timestamp = null): array
{
    $timestamp ??= time();

    return [
        'X-Atx-Ticketing-Timestamp' => (string) $timestamp,
        'X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.', $secret),
    ];
}

/**
 * Plain GET (empty body, like WordPress sends) — getJson would encode a "[]"
 * body and break the signature.
 */
function wpGet(string $uri, array $headers = []): TestResponse
{
    return test()->get($uri, $headers + ['Accept' => 'application/json']);
}

function wpPing(array $headers = []): TestResponse
{
    return wpGet('/api/ticketing/wp/ping', $headers);
}

it('answers a correctly signed ping', function () {
    Event::factory()->create(['status' => EventStatus::Published]);
    Event::factory()->create(['status' => EventStatus::Draft]);

    wpPing(wpSignedHeaders())
        ->assertOk()
        ->assertJson(['ok' => true, 'published_events' => 1]);
});

it('rejects a ping with a wrong secret', function () {
    wpPing(wpSignedHeaders('wrong-secret'))->assertStatus(401);
});

it('rejects a ping without signature headers', function () {
    wpPing()->assertStatus(401);
});

it('rejects a stale ping timestamp', function () {
    wpPing(wpSignedHeaders(timestamp: time() - 3600))->assertStatus(401);
});

it('explains when no secret is configured on the Laravel side', function () {
    config()->set('ticketing.wp_webhook_secret', '');

    wpPing(wpSignedHeaders())->assertStatus(503);
});

it('exports published events for the plugin sync button', function () {
    $published = Event::factory()->create(['status' => EventStatus::Published]);
    EventOccurrence::factory()->for($published, 'event')->create();
    TicketType::factory()->for($published, 'event')->create(['name' => 'Standard', 'base_price' => 2500]);
    Event::factory()->create(['status' => EventStatus::Draft]);

    $response = wpGet('/api/ticketing/wp/events', wpSignedHeaders())->assertOk();

    $events = $response->json('events');

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($published->getKey())
        ->and($events[0]['title'])->toBe($published->title)
        ->and($events[0]['ticket_types'][0]['price'])->toBe(2500);
});

it('rejects an unsigned export request', function () {
    wpGet('/api/ticketing/wp/events')->assertStatus(401);
});
