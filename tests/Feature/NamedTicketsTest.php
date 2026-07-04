<?php

use AtxDigital\Ticketing\Mail\AttendeeTicketMail;
use AtxDigital\Ticketing\Mail\OrderConfirmationMail;
use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use AtxDigital\Ticketing\Models\TicketType;
use AtxDigital\Ticketing\WordPress\EventPayloadBuilder;
use Illuminate\Support\Facades\Mail;
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

it('enforces a ticket-type-scoped question only for that type', function () {
    [$event, $occurrence, $standard] = makePurchasableEvent(['base_price' => 0, 'name' => 'Standard']);
    $vip = TicketType::factory()->for($event, 'event')->create(['name' => 'VIP', 'base_price' => 0]);

    RegistrationQuestion::factory()->required()->create([
        'event_id' => $event->getKey(),
        'ticket_type_ids' => [$vip->getKey()],
        'label' => 'Dinner choice?',
    ]);

    // Buying only Standard: the VIP-only question must NOT block checkout.
    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $standard))
        ->assertCreated();

    // Buying VIP without answering it: blocked.
    $this->postJson(checkoutUrl($event), [
        'occurrence_id' => $occurrence->getKey(),
        'items' => [['ticket_type_id' => $vip->getKey(), 'quantity' => 1]],
        'purchaser' => ['name' => 'Ada', 'email' => 'ada@example.test'],
    ])->assertStatus(422);

    // Buying VIP with the answer: fine.
    $question = RegistrationQuestion::query()->firstOrFail();

    $this->postJson(checkoutUrl($event), [
        'occurrence_id' => $occurrence->getKey(),
        'items' => [['ticket_type_id' => $vip->getKey(), 'quantity' => 1]],
        'purchaser' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        'answers' => [(string) $question->getKey() => 'Fish'],
    ])->assertCreated();
});

it('emails attendees with their own address their personal ticket', function () {
    Mail::fake();

    [$event, $occurrence, $ticketType] = makePurchasableEvent(
        ['base_price' => 0],
        [],
        ['requires_attendee_details' => true],
    );

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2, [
        'attendees' => [
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'Mary Borg', 'email' => 'mary@example.test'],
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'John Vella'],  // no email → buyer only
        ],
    ]))->assertCreated();

    Mail::assertSent(
        OrderConfirmationMail::class,
        fn ($mail) => $mail->hasTo('ada@example.test'),
    );
    Mail::assertSent(
        AttendeeTicketMail::class,
        fn ($mail) => $mail->hasTo('mary@example.test') && $mail->attendee->name === 'Mary Borg',
    );
    // John falls back to the buyer's email → no separate personal mail.
    Mail::assertNotSent(
        AttendeeTicketMail::class,
        fn ($mail) => $mail->attendee->name === 'John Vella',
    );
});
