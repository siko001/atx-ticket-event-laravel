<?php

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Events\DiscountCodeRedeemed;
use AtxDigital\Ticketing\Events\OrderPaid;
use AtxDigital\Ticketing\Events\OrderRefunded;
use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\DiscountCode;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Models\OrderItem;
use AtxDigital\Ticketing\Services\CheckInResult;
use AtxDigital\Ticketing\Services\CheckInService;
use AtxDigital\Ticketing\Services\OrderPaymentService;
use Illuminate\Support\Facades\Event as EventFacade;

it('marks orders paid exactly once', function () {
    EventFacade::fake([OrderPaid::class]);

    $order = Order::factory()->create();

    $service = app(OrderPaymentService::class);
    $service->markPaid($order, 'pi_abc');
    $service->markPaid($order, 'pi_abc');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->stripe_payment_intent_id)->toBe('pi_abc');

    EventFacade::assertDispatchedTimes(OrderPaid::class, 1);
});

it('increments discount code uses and fires DiscountCodeRedeemed on payment', function () {
    EventFacade::fake([OrderPaid::class, DiscountCodeRedeemed::class]);

    $code = DiscountCode::factory()->create(['uses_count' => 0]);
    $order = Order::factory()->create(['discount_code_id' => $code->getKey()]);

    app(OrderPaymentService::class)->markPaid($order);

    expect($code->fresh()->uses_count)->toBe(1);

    EventFacade::assertDispatched(DiscountCodeRedeemed::class, function (DiscountCodeRedeemed $event) use ($code, $order) {
        return $event->discountCode->is($code) && $event->order->is($order);
    });
});

it('marks orders refunded exactly once', function () {
    EventFacade::fake([OrderPaid::class, OrderRefunded::class]);

    $order = Order::factory()->paid()->create();

    $service = app(OrderPaymentService::class);
    $service->markRefunded($order);
    $service->markRefunded($order);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->refunded_at)->not->toBeNull();

    EventFacade::assertDispatchedTimes(OrderRefunded::class, 1);
});

it('cancels pending and free orders idempotently, invalidating check-in', function () {
    $order = Order::factory()->create(); // pending

    $service = app(OrderPaymentService::class);
    $service->markCancelled($order);
    $service->markCancelled($order);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // A cancelled (previously free/paid) order's tickets are no longer scannable.
    $freeOrder = Order::factory()->paid()->create(['total' => 0]);
    $item = OrderItem::factory()->create(['order_id' => $freeOrder->getKey(), 'unit_price' => 0]);
    $attendee = Attendee::factory()->create(['order_item_id' => $item->getKey()]);

    $service->markCancelled($freeOrder);

    $result = app(CheckInService::class)->checkIn($attendee->checkin_token);

    expect($result->status)->toBe(CheckInResult::NOT_PAID);
});
