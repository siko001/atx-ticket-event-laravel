<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Models\CheckIn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CheckInReport extends Report
{
    public function label(): string
    {
        return 'Check-ins';
    }

    public function headers(): array
    {
        return ['Checked in at', 'Attendee', 'Email', 'Event', 'Occurrence', 'Checked in by (user ID)', 'Source'];
    }

    public function rows(ReportFilters $filters): array
    {
        $query = ticketing_model('check_in')::query()
            ->with(['attendee.orderItem.order.event', 'attendee.orderItem.order.occurrence']);

        if ($filters->eventId !== null) {
            $query->whereHas(
                'attendee.orderItem.order',
                fn (Builder $q) => $q->where('event_id', $filters->eventId),
            );
        }

        $filters->applyDates($query, 'checked_in_at');

        /** @var Collection<int, CheckIn> $checkIns */
        $checkIns = $query->orderBy('checked_in_at')->get();

        return $checkIns->map(function ($checkIn) {
            $order = $checkIn->attendee?->orderItem?->order;

            return [
                $checkIn->checked_in_at->format('Y-m-d H:i:s'),
                $checkIn->attendee?->name,
                $checkIn->attendee?->email,
                $order?->event?->title,
                $order?->occurrence?->starts_at?->format('Y-m-d H:i'),
                $checkIn->checked_in_by,
                $checkIn->metadata['source'] ?? '',
            ];
        })->all();
    }
}
