<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Contracts\PaymentVerifierContract;
use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * Compares local order records against the payment gateway and flags
 * mismatches (paid locally but not at the gateway, amount drift, etc.).
 * Note: this report calls the Stripe API once per order in range.
 */
class PaymentReconciliationReport extends Report
{
    public function __construct(protected PaymentVerifierContract $verifier) {}

    public function label(): string
    {
        return 'Payment reconciliation';
    }

    public function headers(): array
    {
        return ['Order', 'Local status', 'Local total', 'Gateway status', 'Gateway amount', 'Match', 'Notes'];
    }

    public function rows(ReportFilters $filters): array
    {
        $query = ticketing_model('order')::query()
            ->whereIn('status', $filters->statusesOr([OrderStatus::Paid, OrderStatus::Refunded, OrderStatus::Pending]))
            ->whereNotNull('stripe_checkout_session_id');

        if ($filters->eventId !== null) {
            $query->where('event_id', $filters->eventId);
        }

        $filters->applyDates($query, 'created_at');

        /** @var Collection<int, Order> $orders */
        $orders = $query->orderBy('created_at')->get();

        return $orders->map(function ($order) {
            $verification = $this->verifier->verify($order);

            return [
                $order->order_number,
                $order->status->value,
                number_format($order->total / 100, 2),
                $verification->gatewayStatus ?? '—',
                $verification->gatewayAmount === null ? '—' : number_format($verification->gatewayAmount / 100, 2),
                $verification->matches($order) ? 'OK' : 'MISMATCH',
                $verification->error ?? '',
            ];
        })->all();
    }
}
