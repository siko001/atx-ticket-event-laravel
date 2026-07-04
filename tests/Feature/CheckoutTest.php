<?php

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Mail\OrderConfirmationMail;
use AtxDigital\Ticketing\Models\DiscountCode;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Models\OrderItem;
use AtxDigital\Ticketing\Models\PricingRule;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = fakeTicketingServices();
    Storage::fake('local');
});

it('creates a pending order and returns the checkout URL', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 5000]);

    $response = $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2));

    $response->assertCreated()
        ->assertJsonStructure(['order_id', 'order_number', 'checkout_url']);

    expect($response->json('checkout_url'))->toContain('checkout.stripe.test');

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->subtotal)->toBe(10000)
        ->and($order->discount_total)->toBe(0)
        ->and($order->total)->toBe(10000)
        ->and($order->stripe_checkout_session_id)->toBe('cs_test_'.$order->getKey())
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and($order->attendees)->toHaveCount(2);

    foreach ($order->attendees as $attendee) {
        expect($attendee->checkin_token)->toHaveLength(32)
            ->and($attendee->name)->toBe('Ada Lovelace');
    }
});

it('applies early bird pricing and a discount code to the totals', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 10000]);

    PricingRule::factory()->create([
        'ticket_type_id' => $ticketType->getKey(),
        'type' => 'early_bird',
        'config' => [
            'starts_at' => now()->subWeek()->toIso8601String(),
            'ends_at' => now()->addWeek()->toIso8601String(),
            'value_type' => 'percentage',
            'value' => 20,
        ],
    ]);

    DiscountCode::factory()->create(['code' => 'SAVE10', 'value' => 10]);

    $response = $this->postJson(
        checkoutUrl($event),
        checkoutPayload($occurrence, $ticketType, 2, ['discount_code' => 'SAVE10']),
    );

    $response->assertCreated();

    $order = Order::query()->findOrFail($response->json('order_id'));

    // 10000 → early bird 20% → 8000/unit; promo 10% → 7200/unit.
    expect($order->subtotal)->toBe(16000)
        ->and($order->discount_total)->toBe(1600)
        ->and($order->total)->toBe(14400)
        ->and($order->items->first()->unit_price)->toBe(7200)
        ->and($order->discountCode?->code)->toBe('SAVE10')
        // Uses are only consumed when the order is actually paid.
        ->and($order->discountCode->fresh()->uses_count)->toBe(0);

    $snapshot = $order->items->first()->pricing_snapshot;
    expect($snapshot['base_price'])->toBe(10000)
        ->and(collect($snapshot['applied_rules'])->pluck('type')->all())->toBe(['early_bird', 'promo_code']);
});

it('adds flat VAT at order level when configured', function () {
    config()->set('ticketing.vat.mode', 'flat');
    config()->set('ticketing.vat.rate', 18);

    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 10000]);

    $response = $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType));

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->vat_total)->toBe(1800)
        ->and($order->total)->toBe(11800);
});

it('requires answers to required registration questions', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent();

    $question = RegistrationQuestion::factory()->required()->create([
        'event_id' => $event->getKey(),
        'label' => 'Dietary requirements?',
    ]);

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["answers.{$question->getKey()}"]);

    $response = $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 1, [
        'answers' => [$question->getKey() => 'Vegetarian'],
    ]));

    $response->assertCreated();

    $order = Order::query()->findOrFail($response->json('order_id'));
    $responseRow = $order->attendees->first()->responses->first();

    expect($responseRow->label)->toBe('Dietary requirements?')
        ->and($responseRow->value)->toBe('Vegetarian')
        ->and($responseRow->registration_question_id)->toBe($question->getKey());
});

it('rejects answers outside a select question\'s options', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent();

    $question = RegistrationQuestion::factory()->required()->select(['S', 'M', 'L'])->create([
        'event_id' => $event->getKey(),
    ]);

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 1, [
        'answers' => [$question->getKey() => 'XXL'],
    ]))->assertUnprocessable();
});

it('rejects orders exceeding occurrence capacity', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(occurrenceAttrs: ['capacity' => 1]);

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occurrence_id']);
});

it('rejects a sold out ticket type', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['quantity_available' => 1]);

    $order = Order::factory()->paid()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'ticket_type_id' => $ticketType->getKey(),
        'quantity' => 1,
    ]);

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);
});

it('rejects an exhausted discount code', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent();

    DiscountCode::factory()->create(['code' => 'GONE', 'max_uses' => 1, 'uses_count' => 1]);

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 1, ['discount_code' => 'GONE']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount_code']);
});

it('rejects checkout for unpublished events', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent();
    $event->forceFill(['status' => 'draft'])->save();

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType))
        ->assertUnprocessable();
});

it('marks free orders paid immediately and fulfils them inline', function () {
    Mail::fake();

    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 0]);

    $response = $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType));

    $response->assertCreated();
    expect($response->json('checkout_url'))->toStartWith('https://shop.test/thanks');

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($this->gateway->sessionsCreatedFor)->toBeEmpty()
        ->and($order->attendees->first()->ticket_pdf_path)->not->toBeNull();

    Mail::assertSent(OrderConfirmationMail::class, 1);
});

it('stores per-attendee details when provided', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent();

    $response = $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2, [
        'attendees' => [
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'Grace Hopper', 'email' => 'grace@example.test'],
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'Alan Turing', 'email' => 'alan@example.test'],
        ],
    ]));

    $response->assertCreated();

    $order = Order::query()->findOrFail($response->json('order_id'));

    expect($order->attendees->pluck('name')->sort()->values()->all())
        ->toBe(['Alan Turing', 'Grace Hopper']);
});

it('rejects attendee details that do not match quantities', function () {
    [$event, $occurrence, $ticketType] = makePurchasableEvent();

    $this->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2, [
        'attendees' => [
            ['ticket_type_id' => $ticketType->getKey(), 'name' => 'Only One', 'email' => 'one@example.test'],
        ],
    ]))->assertUnprocessable();
});

it('returns 404 for unknown events', function () {
    [, $occurrence, $ticketType] = makePurchasableEvent();

    $this->postJson('/api/ticketing/events/999999/checkout', checkoutPayload($occurrence, $ticketType))
        ->assertNotFound();
});
