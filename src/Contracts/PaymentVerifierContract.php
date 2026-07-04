<?php

namespace AtxDigital\Ticketing\Contracts;

use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Payments\PaymentVerification;

interface PaymentVerifierContract
{
    /**
     * Fetch the gateway-side status/amount for an order, for reconciliation.
     */
    public function verify(Order $order): PaymentVerification;
}
