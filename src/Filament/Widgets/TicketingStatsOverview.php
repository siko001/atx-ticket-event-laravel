<?php

namespace AtxDigital\Ticketing\Filament\Widgets;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Enums\OrderStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Dashboard metrics for event data: events, upcoming dates, sales and
 * check-ins at a glance.
 */
class TicketingStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) config('ticketing.features.dashboard_metrics', true);
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $now = Carbon::now();

        $publishedEvents = ticketing_model('event')::query()
            ->where('status', EventStatus::Published)
            ->count();

        $upcomingDates = ticketing_model('event_occurrence')::query()
            ->where('status', OccurrenceStatus::Scheduled)
            ->where('starts_at', '>=', $now)
            ->count();

        $paidOrders = ticketing_model('order')::query()
            ->where('status', OrderStatus::Paid)
            ->where('is_test', false);

        $revenue = (int) (clone $paidOrders)->sum('total');
        $paidCount = (clone $paidOrders)->count();

        $revenue30 = (int) ticketing_model('order')::query()
            ->where('status', OrderStatus::Paid)
            ->where('is_test', false)
            ->where('paid_at', '>=', $now->copy()->subDays(30))
            ->sum('total');

        $ticketsSold = ticketing_model('attendee')::query()
            ->whereHas('orderItem.order', fn ($query) => $query->where('status', OrderStatus::Paid)->where('is_test', false))
            ->count();

        $checkedIn = ticketing_model('attendee')::query()
            ->whereNotNull('checked_in_at')
            ->count();

        $pending = ticketing_model('order')::query()
            ->where('status', OrderStatus::Pending)
            ->where('is_test', false)
            ->count();

        return [
            Stat::make('Published events', (string) $publishedEvents)
                ->description($upcomingDates.' upcoming date(s)')
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Revenue (paid)', ticketing_money($revenue))
                ->description(ticketing_money($revenue30).' in the last 30 days')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Tickets sold', (string) $ticketsSold)
                ->description($paidCount.' paid order(s), '.$pending.' pending')
                ->icon('heroicon-o-ticket'),
            Stat::make('Checked in', (string) $checkedIn)
                ->description($ticketsSold > 0 ? round($checkedIn / $ticketsSold * 100).'% of sold tickets' : 'No tickets sold yet')
                ->icon('heroicon-o-qr-code'),
        ];
    }
}
