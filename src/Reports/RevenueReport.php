<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class RevenueReport extends Report
{
    public function label(): string
    {
        return 'Revenue';
    }

    public function headers(): array
    {
        return ['Event', 'Currency', 'Orders', 'Tickets', 'Subtotal', 'Discount', 'VAT', 'Total'];
    }

    public function rows(ReportFilters $filters): array
    {
        $query = ticketing_model('order')::query()
            ->with(['event', 'items'])
            ->whereIn('status', $filters->statusesOr([OrderStatus::Paid]))
            ->where('is_test', false);

        if ($filters->eventId !== null) {
            $query->where('event_id', $filters->eventId);
        }

        $filters->applyDates($query, 'paid_at');

        /** @var Collection<int, Order> $orders */
        $orders = $query->get();

        return $orders
            ->groupBy(fn ($order) => ($order->event->title ?? 'Unknown').'|'.$order->currency)
            ->map(function ($orders) {
                $first = $orders->first();

                return [
                    $first->event->title ?? 'Unknown',
                    strtoupper($first->currency),
                    $orders->count(),
                    (int) $orders->sum(fn ($order) => $order->items->sum('quantity')),
                    number_format($orders->sum('subtotal') / 100, 2),
                    number_format($orders->sum('discount_total') / 100, 2),
                    number_format($orders->sum('vat_total') / 100, 2),
                    number_format($orders->sum('total') / 100, 2),
                ];
            })
            ->values()
            ->all();
    }
}
