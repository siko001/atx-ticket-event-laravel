<?php

use AtxDigital\Ticketing\Contracts\PaymentGatewayContract;
use AtxDigital\Ticketing\Contracts\PdfGeneratorContract;
use AtxDigital\Ticketing\Contracts\QrCodeGeneratorContract;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Models\TicketType;
use AtxDigital\Ticketing\Tests\Support\FakeGateway;
use AtxDigital\Ticketing\Tests\Support\FakePdfGenerator;
use AtxDigital\Ticketing\Tests\Support\FakeQrCodeGenerator;
use AtxDigital\Ticketing\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Swap the Stripe gateway and asset generators for fakes.
 */
function fakeTicketingServices(): FakeGateway
{
    $gateway = new FakeGateway;

    app()->instance(PaymentGatewayContract::class, $gateway);
    app()->instance(QrCodeGeneratorContract::class, new FakeQrCodeGenerator);
    app()->instance(PdfGeneratorContract::class, new FakePdfGenerator);

    return $gateway;
}

/**
 * @return array{0: Event, 1: EventOccurrence, 2: TicketType}
 */
function makePurchasableEvent(array $ticketTypeAttrs = [], array $occurrenceAttrs = [], array $eventAttrs = []): array
{
    $event = Event::factory()->published()->create($eventAttrs);
    $occurrence = EventOccurrence::factory()->for($event, 'event')->create($occurrenceAttrs);
    $ticketType = TicketType::factory()->for($event, 'event')->create($ticketTypeAttrs);

    return [$event, $occurrence, $ticketType];
}

function checkoutPayload(EventOccurrence $occurrence, TicketType $ticketType, int $quantity = 1, array $overrides = []): array
{
    return array_replace_recursive([
        'occurrence_id' => $occurrence->getKey(),
        'items' => [
            ['ticket_type_id' => $ticketType->getKey(), 'quantity' => $quantity],
        ],
        'purchaser' => [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
        ],
    ], $overrides);
}

function checkoutUrl(Event $event): string
{
    return "/api/ticketing/events/{$event->getKey()}/checkout";
}

function stripeSignatureHeader(string $payload, string $secret = 'whsec_test'): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}
