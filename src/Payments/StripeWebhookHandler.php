<?php

namespace AtxDigital\Ticketing\Payments;

use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Services\OrderPaymentService;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Event as StripeEvent;

/**
 * Interprets verified Stripe events. Kept intentionally thin: it only flips
 * order state and fires domain events — fulfilment (tickets, mail) happens in
 * queued listeners so the webhook responds fast.
 */
class StripeWebhookHandler
{
    public function __construct(protected OrderPaymentService $payments) {}

    public function handle(StripeEvent $event): void
    {
        match ($event->type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->handleCheckoutCompleted($event),
            'charge.refunded' => $this->handleChargeRefunded($event),
            default => null,
        };
    }

    protected function handleCheckoutCompleted(StripeEvent $event): void
    {
        /** @var Session $session */
        $session = $event->data->object;

        if ((string) $session->payment_status !== 'paid') {
            // Async payment methods complete later via async_payment_succeeded.
            return;
        }

        $order = $this->findOrder('stripe_checkout_session_id', (string) $session->id);

        if ($order === null) {
            Log::warning('Stripe webhook received for unknown checkout session.', ['session' => $session->id]);

            return;
        }

        $this->payments->markPaid($order, $session->payment_intent === null ? null : (string) $session->payment_intent);
    }

    protected function handleChargeRefunded(StripeEvent $event): void
    {
        /** @var Charge $charge */
        $charge = $event->data->object;

        $order = $this->findOrder('stripe_payment_intent_id', (string) $charge->payment_intent);

        if ($order === null) {
            Log::warning('Stripe refund webhook received for unknown payment intent.', [
                'payment_intent' => $charge->payment_intent,
            ]);

            return;
        }

        $this->payments->markRefunded($order);
    }

    protected function findOrder(string $column, string $value): ?Order
    {
        if ($value === '') {
            return null;
        }

        /** @var Order|null */
        return ticketing_model('order')::query()->where($column, $value)->first();
    }
}
