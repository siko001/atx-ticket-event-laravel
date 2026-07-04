<?php

namespace AtxDigital\Ticketing\Payments;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\Order;

/**
 * Gateway-side view of an order's payment, used by the reconciliation report.
 */
final readonly class PaymentVerification
{
    public function __construct(
        public ?string $gatewayStatus,
        public ?int $gatewayAmount,
        public ?string $error = null,
    ) {}

    public function matches(Order $order): bool
    {
        if ($this->error !== null) {
            return false;
        }

        return match ($order->status) {
            OrderStatus::Paid, OrderStatus::Refunded => $this->gatewayStatus === 'paid'
                && $this->gatewayAmount === $order->total,
            OrderStatus::Pending, OrderStatus::Cancelled => $this->gatewayStatus !== 'paid',
        };
    }
}
