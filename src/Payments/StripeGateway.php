<?php

namespace AtxDigital\Ticketing\Payments;

use AtxDigital\Ticketing\Contracts\PaymentGatewayContract;
use AtxDigital\Ticketing\Contracts\PaymentVerifierContract;
use AtxDigital\Ticketing\Exceptions\PaymentFailedException;
use AtxDigital\Ticketing\Exceptions\RefundFailedException;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Support\Url;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeGateway implements PaymentGatewayContract, PaymentVerifierContract
{
    public function __construct(protected StripeClient $client) {}

    /**
     * Stripe client for this specific order: its connection's key overrides
     * apply, and test/live follows the order's snapshotted is_test flag.
     */
    protected function clientFor(Order $order): StripeClient
    {
        $secret = StripeKeys::secretForOrder($order);

        if (blank($secret)) {
            throw new PaymentFailedException(StripeKeys::missingKeyHint($order));
        }

        return new StripeClient($secret);
    }

    public function createCheckoutSession(Order $order): string
    {

        $order->loadMissing(['items.ticketType', 'event']);

        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $order->getKey(),
            'customer_email' => $order->purchaser_email,
            'line_items' => $this->lineItems($order),
            'success_url' => $this->returnUrl($order, 'success'),
            'cancel_url' => $this->returnUrl($order, 'cancel'),
            'metadata' => [
                'order_id' => (string) $order->getKey(),
                'order_number' => $order->order_number,
            ],
        ];

        if (config('ticketing.vat.mode') === 'stripe_tax') {
            $params['automatic_tax'] = ['enabled' => true];
        }

        try {
            $session = $this->clientFor($order)->checkout->sessions->create($params);
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed.', [
                'order' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);

            throw PaymentFailedException::wrap($e);
        }

        $order->forceFill(['stripe_checkout_session_id' => $session->id])->save();

        return (string) $session->url;
    }

    public function refund(Order $order, ?int $amount = null): void
    {
        if (blank($order->stripe_payment_intent_id)) {
            throw new RefundFailedException("Order {$order->order_number} has no payment intent to refund.");
        }

        $params = ['payment_intent' => $order->stripe_payment_intent_id];

        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        try {
            $this->clientFor($order)->refunds->create($params);
        } catch (ApiErrorException $e) {
            Log::error('Stripe refund failed.', [
                'order' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);

            throw RefundFailedException::wrap($e);
        }
    }

    public function verify(Order $order): PaymentVerification
    {
        if (blank(StripeKeys::secretForOrder($order))) {
            return new PaymentVerification(null, null, StripeKeys::missingKeyHint($order));
        }

        if (blank($order->stripe_checkout_session_id)) {
            return new PaymentVerification(null, null, 'No Stripe checkout session recorded.');
        }

        try {
            $session = $this->clientFor($order)->checkout->sessions->retrieve($order->stripe_checkout_session_id);
        } catch (ApiErrorException $e) {
            return new PaymentVerification(null, null, $e->getMessage());
        }

        return new PaymentVerification(
            gatewayStatus: (string) $session->payment_status,
            gatewayAmount: $session->amount_total === null ? null : (int) $session->amount_total,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lineItems(Order $order): array
    {
        $lineItems = [];

        foreach ($order->items as $item) {
            $productData = ['name' => $item->ticketType->name ?? 'Ticket'];

            if (filled($order->event?->title)) {
                $productData['description'] = $order->event->title;
            }

            $lineItems[] = [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $this->discountedUnitPrice($order, $item->unit_price),
                    'product_data' => $productData,
                ],
            ];
        }

        if (config('ticketing.vat.mode') === 'flat' && $order->vat_total > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $order->vat_total,
                    'product_data' => [
                        'name' => sprintf('VAT (%s%%)', rtrim(rtrim(number_format((float) config('ticketing.vat.rate'), 2), '0'), '.')),
                    ],
                ],
            ];
        }

        return $lineItems;
    }

    /**
     * Order item unit prices are stored net of discount codes, so they can be
     * passed straight through as Stripe line item amounts.
     */
    protected function discountedUnitPrice(Order $order, int $unitPrice): int
    {
        return $unitPrice;
    }

    protected function returnUrl(Order $order, string $kind): string
    {
        $url = $kind === 'success'
            ? ($order->success_url ?: config('ticketing.checkout.success_url'))
            : ($order->cancel_url ?: config('ticketing.checkout.cancel_url'));

        if (blank($url)) {
            throw new PaymentFailedException(
                "No {$kind} URL configured. Set ticketing.checkout.{$kind}_url or pass one at checkout."
            );
        }

        return Url::appendQuery((string) $url, ['order' => $order->order_number]);
    }
}
