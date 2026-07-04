<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\DiscountCode;
use Illuminate\Database\Eloquent\Collection;

class DiscountCodeUsageReport extends Report
{
    public function label(): string
    {
        return 'Discount code usage';
    }

    public function headers(): array
    {
        return ['Code', 'Type', 'Value', 'Uses', 'Max uses', 'Paid orders', 'Total discounted'];
    }

    public function rows(ReportFilters $filters): array
    {
        /** @var Collection<int, DiscountCode> $codes */
        $codes = ticketing_model('discount_code')::query()->orderBy('code')->get();

        return $codes
            ->map(function ($code) use ($filters) {
                $ordersQuery = $code->orders()->where('status', OrderStatus::Paid);

                if ($filters->eventId !== null) {
                    $ordersQuery->where('event_id', $filters->eventId);
                }

                $filters->applyDates($ordersQuery, 'paid_at');

                $orders = $ordersQuery->get();

                return [
                    $code->code,
                    $code->type->value,
                    $code->value,
                    $code->uses_count,
                    $code->max_uses ?? 'Unlimited',
                    $orders->count(),
                    number_format($orders->sum('discount_total') / 100, 2),
                ];
            })
            ->all();
    }
}
