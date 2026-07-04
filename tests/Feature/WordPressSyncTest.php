<?php

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Models\TicketType;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('ticketing.wp_webhook_url', 'https://wp.test/wp-json/atx-ticketing/v1/webhook');
    config()->set('ticketing.wp_webhook_secret', 'shared-secret');

    Http::fake(['wp.test/*' => Http::response(['ok' => true])]);
});

function assertValidWpSignature(Request $request): void
{
    $timestamp = $request->header('X-Atx-Ticketing-Timestamp')[0] ?? '';
    $signature = $request->header('X-Atx-Ticketing-Signature')[0] ?? '';

    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'shared-secret');

    expect($signature)->toBe($expected);
}

it('pushes a signed event.published payload when an event is published', function () {
    $event = Event::factory()->create();
    EventOccurrence::factory()->for($event, 'event')->create();
    TicketType::factory()->for($event, 'event')->create(['name' => 'Standard', 'base_price' => 2500]);

    $event->update(['status' => EventStatus::Published]);

    Http::assertSent(function (Request $request) use ($event) {
        if ($request->url() !== 'https://wp.test/wp-json/atx-ticketing/v1/webhook') {
            return false;
        }

        assertValidWpSignature($request);

        $payload = $request->data();

        return $payload['type'] === 'event.published'
            && $payload['event']['id'] === $event->getKey()
            && $payload['event']['title'] === $event->title
            && count($payload['event']['occurrences']) === 1
            && $payload['event']['ticket_types'][0]['name'] === 'Standard'
            && $payload['event']['ticket_types'][0]['price'] === 2500
            && str_contains($payload['event']['checkout_url'], "/api/ticketing/events/{$event->getKey()}/checkout");
    });
});

it('pushes event.updated when a published event changes', function () {
    $event = Event::factory()->published()->create();

    $event->update(['title' => 'New title']);

    Http::assertSent(fn (Request $request) => $request->data()['type'] === 'event.updated'
        && $request->data()['event']['title'] === 'New title');
});

it('pushes event.cancelled when a published event is cancelled', function () {
    $event = Event::factory()->published()->create();

    $event->update(['status' => EventStatus::Cancelled]);

    Http::assertSent(fn (Request $request) => $request->data()['type'] === 'event.cancelled');
});

it('pushes event.deleted with just the id when an event is deleted', function () {
    $event = Event::factory()->published()->create();
    $id = $event->getKey();

    $event->delete();

    Http::assertSent(fn (Request $request) => $request->data()['type'] === 'event.deleted'
        && $request->data()['event'] === ['id' => $id]);
});

it('does not push anything when no webhook URL is configured', function () {
    config()->set('ticketing.wp_webhook_url', null);

    Event::factory()->published()->create();

    Http::assertNothingSent();
});
