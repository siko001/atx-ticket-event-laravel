<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Models\Order;

final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public string $checkoutUrl,
    ) {}
}
