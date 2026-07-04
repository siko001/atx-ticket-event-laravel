<?php

namespace AtxDigital\Ticketing\Events;

use AtxDigital\Ticketing\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Order $order) {}
}
