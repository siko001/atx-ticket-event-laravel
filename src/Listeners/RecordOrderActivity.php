<?php

namespace AtxDigital\Ticketing\Listeners;

use AtxDigital\Ticketing\Events\OrderCreated;
use AtxDigital\Ticketing\Events\OrderPaid;
use AtxDigital\Ticketing\Events\OrderRefunded;
use AtxDigital\Ticketing\Support\ActivityLogger;

/**
 * Writes "order" channel activity log entries for the buy flow.
 */
class RecordOrderActivity
{
    public function handle(OrderCreated|OrderPaid|OrderRefunded $domainEvent): void
    {
        $order = $domainEvent->order;

        [$action, $level] = match ($domainEvent::class) {
            OrderCreated::class => ['created', 'info'],
            OrderPaid::class => ['paid', 'info'],
            OrderRefunded::class => ['refunded', 'warning'],
            default => ['updated', 'info'],
        };

        ActivityLogger::order(
            "Order {$order->order_number} {$action} — ".ticketing_money((int) $order->total, (string) $order->currency),
            [
                'order_id' => $order->getKey(),
                'order_number' => $order->order_number,
                'event_id' => $order->event_id,
                'purchaser_email' => $order->purchaser_email,
                'total' => $order->total,
                'status' => $action,
            ],
            $level,
        );
    }
}
