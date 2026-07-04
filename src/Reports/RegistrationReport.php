<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\Attendee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Attendee-level export: one row per ticket holder, including the purchaser
 * and every registration answer — the "who exactly is coming" report.
 * Defaults to pending + paid orders; use the status filter to narrow/widen.
 */
class RegistrationReport extends Report
{
    public function label(): string
    {
        return 'Registrations (attendee details)';
    }

    public function headers(): array
    {
        return [
            'Order', 'Order status', 'Purchaser', 'Purchaser email',
            'Event', 'Occurrence', 'Ticket type',
            'Attendee', 'Attendee email', 'Organisation', 'Country',
            'Answers', 'Checked in at', 'Registered at',
        ];
    }

    public function rows(ReportFilters $filters): array
    {
        $statuses = $filters->statusesOr([OrderStatus::Pending, OrderStatus::Paid]);

        $query = ticketing_model('attendee')::query()
            ->with(['responses', 'orderItem.ticketType', 'orderItem.order.event', 'orderItem.order.occurrence'])
            ->whereHas('orderItem.order', function (Builder $q) use ($filters, $statuses) {
                $q->whereIn('status', $statuses);

                if ($filters->eventId !== null) {
                    $q->where('event_id', $filters->eventId);
                }
            });

        $filters->applyDates($query, 'ticketing_attendees.created_at');

        /** @var Collection<int, Attendee> $attendees */
        $attendees = $query->orderBy('created_at')->get();

        return $attendees->map(function ($attendee) {
            $order = $attendee->orderItem?->order;

            $answers = $attendee->responses
                ->map(fn ($response) => $response->label.': '.($response->value ?? '—'))
                ->join(' | ');

            return [
                $order?->order_number,
                $order?->status->value,
                $order?->purchaser_name,
                $order?->purchaser_email,
                $order?->event?->title,
                $order?->occurrence?->starts_at?->format('Y-m-d H:i'),
                $attendee->orderItem?->ticketType?->name,
                $attendee->name,
                $attendee->email,
                $attendee->organisation,
                $attendee->country,
                $answers,
                $attendee->checked_in_at?->format('Y-m-d H:i'),
                $attendee->created_at?->format('Y-m-d H:i'),
            ];
        })->all();
    }
}
