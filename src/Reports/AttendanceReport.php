<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Collection;

class AttendanceReport extends Report
{
    public function label(): string
    {
        return 'Attendance';
    }

    public function headers(): array
    {
        return ['Event', 'Occurrence', 'Capacity', 'Registered', 'Checked in', 'Check-in rate'];
    }

    public function rows(ReportFilters $filters): array
    {
        $query = ticketing_model('event_occurrence')::query()->with('event');

        if ($filters->eventId !== null) {
            $query->where('event_id', $filters->eventId);
        }

        $filters->applyDates($query, 'starts_at');

        /** @var Collection<int, EventOccurrence> $occurrences */
        $occurrences = $query->orderBy('starts_at')->get();

        $rows = [];

        foreach ($occurrences as $occurrence) {
            $paid = $occurrence->attendeeQuery([OrderStatus::Paid]);
            $registered = (clone $paid)->count();
            $checkedIn = (clone $paid)->whereNotNull('checked_in_at')->count();

            $rows[] = [
                $occurrence->event->title ?? 'Unknown',
                $occurrence->starts_at->format('Y-m-d H:i'),
                $occurrence->effectiveCapacity() ?? 'Unlimited',
                $registered,
                $checkedIn,
                $registered === 0 ? '0%' : round($checkedIn / $registered * 100).'%',
            ];
        }

        return $rows;
    }
}
