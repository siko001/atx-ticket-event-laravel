<?php

namespace AtxDigital\Ticketing\Events;

use AtxDigital\Ticketing\Models\DiscountCode;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DiscountCodeRedeemed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DiscountCode $discountCode,
        public Order $order,
    ) {}
}
