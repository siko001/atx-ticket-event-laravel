<?php

use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\WordPress\EventPayloadBuilder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    fakeTicketingServices();
    Storage::fake('local');
});

it('rejects checkout without attendee names when the event requires them', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(
        ['base_price' => 0],
        [],
        ['requires_attendee_details' => true],
    );

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2))
        ->assertStatus(422)
        ->assertJsonValidationErrors(["attendees.{$ticketType->getKey()}"]);
});

it('rejects a blank attendee name when the event requires them', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(
        ['base_price' => 0],
        [],
        ['requires_attendee_details' => true],
    );

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2, [
        'attendees' => [
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'Mary Borg'],
            ['ticket_type_id' => $ticketType->getKey(), 'name' => ''],
        ],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['attendees.1.name']);
});

it('creates one named attendee per ticket when required', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(
        ['base_price' => 0],
        [],
        ['requires_attendee_details' => true],
    );

    $response = $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2, [
        'attendees' => [
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'Mary Borg', 'email' => 'mary@example.test'],
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'John Vella'],
        ],
    ]));

    $response->assertCreated();

    $attendees = Attendee::query()->orderBy('id')->get();

    expect($attendees)->toHaveCount(2)
        ->and($attendees[0]->name)->toBe('Mary Borg')
        ->and($attendees[0]->email)->toBe('mary@example.test')
        ->and($attendees[1]->name)->toBe('John Vella')
        // No email given → falls back to the purchaser as contact.
        ->and($attendees[1]->email)->toBe('ada@example.test');
});

it('still falls back to the purchaser when the event does not require names', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 0]);

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2))
        ->assertCreated();

    $attendees = Attendee::query()->get();

    expect($attendees)->toHaveCount(2)
        ->and($attendees->pluck('name')->unique()->all())->toBe(['Ada Lovelace']);
});

it('exposes the flag in the WordPress payload', function () {
    [$event] = makePurchasableEvent([], [], ['requires_attendee_details' => true]);

    $payload = app(EventPayloadBuilder::class)->build($event);

    expect($payload['requires_attendee_details'])->toBeTrue();
});
