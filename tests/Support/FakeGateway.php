<?php

namespace AtxDigital\Ticketing\Tests\Support;

use AtxDigital\Ticketing\Contracts\PaymentGatewayContract;
use AtxDigital\Ticketing\Models\Order;

class FakeGateway implements PaymentGatewayContract
{
    /**
     * @var array<int, int|string>
     */
    public array $sessionsCreatedFor = [];

    /**
     * @var array<int, array{0: int|string, 1: int|null}>
     */
    public array $refunds = [];

    public function createCheckoutSession(Order $order): string
    {
        $order->forceFill(['stripe_checkout_session_id' => 'cs_test_'.$order->getKey()])->save();
        $this->sessionsCreatedFor[] = $order->getKey();

        return 'https://checkout.stripe.test/pay/cs_test_'.$order->getKey();
    }

    public function refund(Order $order, ?int $amount = null): void
    {
        $this->refunds[] = [$order->getKey(), $amount];
    }
}
