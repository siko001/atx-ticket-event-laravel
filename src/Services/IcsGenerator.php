<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Models\EventOccurrence;

/**
 * Minimal RFC 5545 calendar invite builder — single VEVENT, UTC times,
 * proper text escaping and 75-octet line folding.
 */
class IcsGenerator
{
    public function forOccurrence(EventOccurrence $occurrence, ?string $uid = null): string
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        $uid ??= sprintf('occurrence-%d@%s', $occurrence->getKey(), parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'ticketing');

        $location = trim(implode(', ', array_filter([
            $event?->venue_name,
            $event?->venue_address,
        ])));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ATX Digital//Ticketing//EN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$this->escape($uid),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$occurrence->starts_at->copy()->utc()->format('Ymd\THis\Z'),
        ];

        if ($occurrence->ends_at !== null) {
            $lines[] = 'DTEND:'.$occurrence->ends_at->copy()->utc()->format('Ymd\THis\Z');
        }

        $lines[] = 'SUMMARY:'.$this->escape((string) $event?->title);

        if (filled($event?->description)) {
            $lines[] = 'DESCRIPTION:'.$this->escape(strip_tags((string) $event->description));
        }

        if ($location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    protected function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n'],
            $text,
        );
    }

    /**
     * Fold content lines longer than 75 octets (RFC 5545 §3.1).
     */
    protected function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = mb_strcut($line, 0, 75);
        $rest = substr($line, strlen($folded));

        while ($rest !== '') {
            $chunk = mb_strcut($rest, 0, 74);
            $folded .= "\r\n ".$chunk;
            $rest = substr($rest, strlen($chunk));
        }

        return $folded;
    }
}
