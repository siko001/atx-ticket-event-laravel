<?php

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Mail\OrderConfirmationMail;
use AtxDigital\Ticketing\Models\DiscountCode;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->gateway = fakeTicketingServices();
    Storage::fake('local');
    Mail::fake();
});

function postStripeWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

    return test()->call('POST', '/api/ticketing/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature ?? stripeSignatureHeader($body),
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

function checkoutSessionCompletedPayload(Order $order): array
{
    return [
        'id' => 'evt_test_1',
        'object' => 'event',
        'api_version' => '2024-06-20',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => $order->stripe_checkout_session_id,
                'object' => 'checkout.session',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_test_123',
            ],
        ],
    ];
}

function pendingOrderViaCheckout(array $extra = []): Order
{
    [$event, $occurrence, $ticketType] = makePurchasableEvent(['base_price' => 5000]);

    $response = test()->postJson(checkoutUrl($event), checkoutPayload($occurrence, $ticketType, 2, $extra));

    return Order::query()->findOrFail($response->json('order_id'));
}

it('marks the order paid and fulfils tickets on checkout.session.completed', function () {
    $order = pendingOrderViaCheckout();

    postStripeWebhook(checkoutSessionCompletedPayload($order))->assertNoContent();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->stripe_payment_intent_id)->toBe('pi_test_123');

    foreach ($order->attendees as $attendee) {
        expect($attendee->ticket_pdf_path)->not->toBeNull();
        Storage::disk('local')->assertExists($attendee->ticket_pdf_path);
    }

    Mail::assertSent(OrderConfirmationMail::class, 1);
    Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($order) {
        // 2 ticket PDFs + 1 calendar invite.
        return $mail->order->is($order) && count($mail->attachments()) === 3;
    });
});

it('is idempotent across duplicate webhook deliveries', function () {
    $order = pendingOrderViaCheckout();

    postStripeWebhook(checkoutSessionCompletedPayload($order))->assertNoContent();
    postStripeWebhook(checkoutSessionCompletedPayload($order))->assertNoContent();

    Mail::assertSent(OrderConfirmationMail::class, 1);
});

it('redeems the discount code exactly once when paid', function () {
    $code = DiscountCode::factory()->create(['code' => 'SAVE10', 'value' => 10, 'max_uses' => 5]);

    $order = pendingOrderViaCheckout(['discount_code' => 'SAVE10']);

    postStripeWebhook(checkoutSessionCompletedPayload($order));
    postStripeWebhook(checkoutSessionCompletedPayload($order));

    expect($code->fresh()->uses_count)->toBe(1);
});

it('rejects webhooks with an invalid signature', function () {
    $order = pendingOrderViaCheckout();

    postStripeWebhook(checkoutSessionCompletedPayload($order), 't=1,v1=bogus')->assertBadRequest();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('ignores sessions that are not yet paid', function () {
    $order = pendingOrderViaCheckout();

    $payload = checkoutSessionCompletedPayload($order);
    $payload['data']['object']['payment_status'] = 'unpaid';

    postStripeWebhook($payload)->assertNoContent();

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('marks the order refunded on charge.refunded', function () {
    $order = pendingOrderViaCheckout();
    postStripeWebhook(checkoutSessionCompletedPayload($order));

    postStripeWebhook([
        'id' => 'evt_test_2',
        'object' => 'event',
        'type' => 'charge.refunded',
        'data' => [
            'object' => [
                'id' => 'ch_test_1',
                'object' => 'charge',
                'payment_intent' => 'pi_test_123',
            ],
        ],
    ])->assertNoContent();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->refunded_at)->not->toBeNull();
});
