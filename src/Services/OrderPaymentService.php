<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Events\DiscountCodeRedeemed;
use AtxDigital\Ticketing\Events\OrderPaid;
use AtxDigital\Ticketing\Events\OrderRefunded;
use AtxDigital\Ticketing\Models\DiscountCode;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    /**
     * Idempotent: calling twice (e.g. a retried webhook) is a no-op.
     */
    public function markPaid(Order $order, ?string $paymentIntentId = null): void
    {
        if ($order->status === OrderStatus::Paid) {
            return;
        }

        /** @var DiscountCode|null $discountCode */
        $discountCode = null;

        DB::transaction(function () use ($order, $paymentIntentId, &$discountCode) {
            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'stripe_payment_intent_id' => $paymentIntentId ?? $order->stripe_payment_intent_id,
            ])->save();

            if ($order->discount_code_id !== null) {
                $discountCode = ticketing_model('discount_code')::query()
                    ->lockForUpdate()
                    ->find($order->discount_code_id);

                $discountCode?->increment('uses_count');
            }
        });

        if ($discountCode instanceof DiscountCode) {
            event(new DiscountCodeRedeemed($discountCode, $order));
        }

        event(new OrderPaid($order));
    }

    /**
     * Cancel an order without touching the payment gateway — used for free
     * orders (nothing to refund) and abandoned pending orders. Cancelling
     * releases the attendees' capacity and their tickets stop being
     * scannable (check-in requires a paid order). Idempotent.
     */
    public function markCancelled(Order $order): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            return;
        }

        $order->forceFill(['status' => OrderStatus::Cancelled])->save();

        ActivityLogger::order(
            "Order {$order->order_number} cancelled.",
            ['order_id' => $order->getKey(), 'order_number' => $order->order_number, 'status' => 'cancelled'],
            'warning',
        );
    }

    /**
     * Idempotent: repeated refund webhooks are a no-op.
     */
    public function markRefunded(Order $order): void
    {
        if ($order->status === OrderStatus::Refunded) {
            return;
        }

        $order->forceFill([
            'status' => OrderStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        event(new OrderRefunded($order));
    }
}
