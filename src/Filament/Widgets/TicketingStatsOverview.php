<?php

namespace AtxDigital\Ticketing\Filament\Widgets;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Enums\OrderStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Dashboard metrics for event data: events, upcoming dates, sales and
 * check-ins at a glance.
 *
 * Each card exposes a <select> in its label so the viewer can swap the
 * metric shown for that slot (e.g. show "Draft events" instead of
 * "Published events"). The selection is Livewire state, so changing it
 * re-runs {@see getStats()} and recomputes only that card.
 */
class TicketingStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    /**
     * Selected metric key per card slot, bound to each card's <select>
     * via wire:model.live. Empty keys fall back to the slot default.
     *
     * @var array<string, string>
     */
    public array $selectedMetrics = [];

    public static function canView(): bool
    {
        return (bool) config('ticketing.features.dashboard_metrics', true);
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $labels = $this->metricLabels();
        $icons = $this->metricIcons();

        $stats = [];

        foreach ($this->slots() as $slot => [$default, $options]) {
            $key = $this->selectedMetrics[$slot] ?? $default;

            // Guard against stale/tampered keys that aren't valid for this slot.
            if (! in_array($key, $options, true)) {
                $key = $default;
            }

            [$value, $description] = $this->resolveMetric($key);

            $stats[] = Stat::make($this->selectField($slot, $options, $key, $labels), $value)
                ->description($description)
                ->icon($icons[$key] ?? null);
        }

        return $stats;
    }

    /**
     * Card slots: each maps to a default metric key and the list of metric
     * keys the viewer may switch between for that card.
     *
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    protected function slots(): array
    {
        return [
            'events' => ['published_events', ['published_events', 'draft_events', 'total_events', 'upcoming_dates']],
            'revenue' => ['revenue_all', ['revenue_all', 'revenue_30d', 'revenue_7d', 'refunded']],
            'tickets' => ['tickets_sold', ['tickets_sold', 'paid_orders', 'pending_orders']],
            'checkin' => ['checked_in', ['checked_in', 'not_checked_in', 'checkin_rate']],
        ];
    }

    /**
     * Human labels for every metric key (used for both the card label and
     * the <option> text). Kept cheap so it can list all options without
     * running queries.
     *
     * @return array<string, string>
     */
    protected function metricLabels(): array
    {
        return [
            'published_events' => 'Published events',
            'draft_events' => 'Draft events',
            'total_events' => 'Total events',
            'upcoming_dates' => 'Upcoming dates',
            'revenue_all' => 'Revenue (all time)',
            'revenue_30d' => 'Revenue (30 days)',
            'revenue_7d' => 'Revenue (7 days)',
            'refunded' => 'Refunded',
            'tickets_sold' => 'Tickets sold',
            'paid_orders' => 'Paid orders',
            'pending_orders' => 'Pending orders',
            'checked_in' => 'Checked in',
            'not_checked_in' => 'Not checked in',
            'checkin_rate' => 'Check-in rate',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function metricIcons(): array
    {
        return [
            'published_events' => 'heroicon-o-calendar-days',
            'draft_events' => 'heroicon-o-calendar-days',
            'total_events' => 'heroicon-o-calendar-days',
            'upcoming_dates' => 'heroicon-o-calendar-days',
            'revenue_all' => 'heroicon-o-banknotes',
            'revenue_30d' => 'heroicon-o-banknotes',
            'revenue_7d' => 'heroicon-o-banknotes',
            'refunded' => 'heroicon-o-arrow-uturn-left',
            'tickets_sold' => 'heroicon-o-ticket',
            'paid_orders' => 'heroicon-o-shopping-bag',
            'pending_orders' => 'heroicon-o-clock',
            'checked_in' => 'heroicon-o-qr-code',
            'not_checked_in' => 'heroicon-o-user-minus',
            'checkin_rate' => 'heroicon-o-chart-pie',
        ];
    }

    /**
     * Compute the displayed value and description for a metric key.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveMetric(string $key): array
    {
        return match ($key) {
            'published_events' => [
                (string) $this->countEvents(EventStatus::Published),
                $this->countUpcomingDates().' upcoming date(s)',
            ],
            'draft_events' => [
                (string) $this->countEvents(EventStatus::Draft),
                'Not yet published',
            ],
            'total_events' => [
                (string) $this->countEvents(null),
                'All statuses',
            ],
            'upcoming_dates' => [
                (string) $this->countUpcomingDates(),
                'Scheduled dates ahead',
            ],
            'revenue_all' => [
                ticketing_money($this->sumRevenue()),
                $this->countOrders(OrderStatus::Paid).' paid order(s)',
            ],
            'revenue_30d' => [
                ticketing_money($this->sumRevenue(30)),
                'In the last 30 days',
            ],
            'revenue_7d' => [
                ticketing_money($this->sumRevenue(7)),
                'In the last 7 days',
            ],
            'refunded' => [
                ticketing_money($this->sumRevenue(status: OrderStatus::Refunded)),
                'Total refunded',
            ],
            'tickets_sold' => [
                (string) $this->countTicketsSold(),
                $this->countOrders(OrderStatus::Paid).' paid order(s), '.$this->countOrders(OrderStatus::Pending).' pending',
            ],
            'paid_orders' => [
                (string) $this->countOrders(OrderStatus::Paid),
                'Completed orders',
            ],
            'pending_orders' => [
                (string) $this->countOrders(OrderStatus::Pending),
                'Awaiting payment',
            ],
            'checked_in' => [
                (string) $this->countCheckedIn(),
                $this->checkinDescription(),
            ],
            'not_checked_in' => [
                (string) max($this->countTicketsSold() - $this->countCheckedIn(), 0),
                'Yet to arrive',
            ],
            'checkin_rate' => [
                $this->checkinRate(),
                'Of sold tickets',
            ],
            default => ['—', ''],
        };
    }

    /**
     * Build the <select> shown as a card label. Rendered raw (HtmlString)
     * inside Filament's label span; wire:model.live re-runs getStats().
     *
     * @param  array<int, string>  $options
     * @param  array<string, string>  $labels
     */
    protected function selectField(string $slot, array $options, string $current, array $labels): HtmlString
    {
        $optionsHtml = '';

        foreach ($options as $key) {
            $optionsHtml .= sprintf(
                '<option value="%s"%s>%s</option>',
                e($key),
                $key === $current ? ' selected' : '',
                e($labels[$key] ?? $key),
            );
        }

        return new HtmlString(sprintf(
            '<select wire:model.live="selectedMetrics.%s" class="-ms-0.5 cursor-pointer border-0 bg-transparent p-0 pe-6 text-sm font-medium text-gray-500 focus:ring-0 dark:text-gray-400">%s</select>',
            e($slot),
            $optionsHtml,
        ));
    }

    protected function countEvents(?EventStatus $status): int
    {
        $query = ticketing_model('event')::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    protected function countUpcomingDates(): int
    {
        return ticketing_model('event_occurrence')::query()
            ->where('status', OccurrenceStatus::Scheduled)
            ->where('starts_at', '>=', Carbon::now())
            ->count();
    }

    protected function sumRevenue(?int $withinDays = null, OrderStatus $status = OrderStatus::Paid): int
    {
        $query = ticketing_model('order')::query()
            ->where('status', $status)
            ->where('is_test', false);

        if ($withinDays !== null) {
            $query->where('paid_at', '>=', Carbon::now()->subDays($withinDays));
        }

        return (int) $query->sum('total');
    }

    protected function countOrders(OrderStatus $status): int
    {
        return ticketing_model('order')::query()
            ->where('status', $status)
            ->where('is_test', false)
            ->count();
    }

    protected function countTicketsSold(): int
    {
        return ticketing_model('attendee')::query()
            ->whereHas('orderItem.order', fn ($query) => $query->where('status', OrderStatus::Paid)->where('is_test', false))
            ->count();
    }

    protected function countCheckedIn(): int
    {
        return ticketing_model('attendee')::query()
            ->whereNotNull('checked_in_at')
            ->count();
    }

    protected function checkinDescription(): string
    {
        $sold = $this->countTicketsSold();

        return $sold > 0
            ? round($this->countCheckedIn() / $sold * 100).'% of sold tickets'
            : 'No tickets sold yet';
    }

    protected function checkinRate(): string
    {
        $sold = $this->countTicketsSold();

        return $sold > 0
            ? round($this->countCheckedIn() / $sold * 100).'%'
            : '0%';
    }
}
