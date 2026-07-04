<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use RRule\RRule;

/**
 * Expands an event's RRULE into concrete EventOccurrence rows on a rolling
 * window. RRULE parsing/expansion is delegated to rlanvin/php-rrule — DST
 * transitions and nth-weekday rules are not hand-rolled here.
 *
 * The event's earliest occurrence acts as the template: its start supplies
 * DTSTART (in the event's timezone) and its duration is copied to generated
 * occurrences.
 */
class OccurrenceMaterializer
{
    public function materialize(Event $event, ?CarbonInterface $until = null): int
    {
        if (! $event->is_recurring || blank($event->recurrence_rule)) {
            return 0;
        }

        /** @var EventOccurrence|null $template */
        $template = $event->occurrences()->orderBy('starts_at')->first();

        if ($template === null) {
            return 0;
        }

        $timezone = new DateTimeZone($event->timezone ?: 'UTC');
        $dtstart = $template->starts_at->copy()->setTimezone($timezone);
        $durationSeconds = $template->ends_at === null
            ? null
            : (int) $template->starts_at->diffInSeconds($template->ends_at);

        $until ??= now()->addMonths((int) config('ticketing.recurrence.window_months', 12));

        $rrule = new RRule($this->parseRuleParts((string) $event->recurrence_rule, $dtstart->toDateTime()));

        $created = 0;

        foreach ($rrule->getOccurrencesBetween(now()->setTimezone($timezone), $until->copy()->setTimezone($timezone), 1000) as $start) {
            $startsAtUtc = Carbon::instance($start)->utc();

            $occurrence = $event->occurrences()->firstOrCreate(
                ['starts_at' => $startsAtUtc],
                [
                    'ends_at' => $durationSeconds === null ? null : $startsAtUtc->copy()->addSeconds($durationSeconds),
                    'status' => OccurrenceStatus::Scheduled,
                ],
            );

            if ($occurrence->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Parse "FREQ=WEEKLY;BYDAY=MO" (with or without a leading "RRULE:") into
     * php-rrule array parts, injecting DTSTART.
     *
     * @return array<string, mixed>
     */
    protected function parseRuleParts(string $rule, \DateTimeInterface $dtstart): array
    {
        $rule = preg_replace('/^RRULE:/i', '', trim($rule)) ?? '';
        $parts = ['DTSTART' => $dtstart];

        foreach (array_filter(explode(';', $rule)) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $parts[strtoupper(trim($key))] = strtoupper(trim($value));
        }

        return $parts;
    }
}
