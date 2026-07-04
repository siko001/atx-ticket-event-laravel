<?php

namespace AtxDigital\Ticketing\Contracts;

use AtxDigital\Ticketing\Exceptions\PaymentFailedException;
use AtxDigital\Ticketing\Exceptions\RefundFailedException;
use AtxDigital\Ticketing\Models\Order;

interface PaymentGatewayContract
{
    /**
     * Create a hosted checkout session for the order and return its URL.
     * Implementations must persist the session reference on the order.
     *
     * @throws PaymentFailedException
     */
    public function createCheckoutSession(Order $order): string;

    /**
     * Refund the order (full refund when $amount is null; minor units otherwise).
     *
     * @throws RefundFailedException
     */
    public function refund(Order $order, ?int $amount = null): void;
}
