<?php

use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Services\IcsGenerator;

it('builds a valid single-event calendar', function () {
    $event = new Event([
        'title' => 'Design, Code & Coffee',
        'timezone' => 'UTC',
        'venue_name' => 'The Hall',
        'venue_address' => '1 Main Street; Valletta',
        'description' => '<p>Bring a laptop.</p>',
    ]);

    $occurrence = new EventOccurrence([
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 20:00:00',
    ]);
    $occurrence->setRelation('event', $event);

    $ics = app(IcsGenerator::class)->forOccurrence($occurrence, 'test-uid@example.test');

    expect($ics)
        ->toContain("BEGIN:VCALENDAR\r\n")
        ->toContain('BEGIN:VEVENT')
        ->toContain('UID:test-uid@example.test')
        ->toContain('DTSTART:20260801T180000Z')
        ->toContain('DTEND:20260801T200000Z')
        ->toContain('SUMMARY:Design\\, Code & Coffee')
        ->toContain('LOCATION:The Hall\\, 1 Main Street\\; Valletta')
        ->toContain('DESCRIPTION:Bring a laptop.')
        ->toContain("END:VCALENDAR\r\n");
});

it('folds long lines at 75 octets', function () {
    $event = new Event([
        'title' => str_repeat('Really long event title ', 10),
        'timezone' => 'UTC',
    ]);

    $occurrence = new EventOccurrence(['starts_at' => '2026-08-01 18:00:00']);
    $occurrence->setRelation('event', $event);

    $ics = app(IcsGenerator::class)->forOccurrence($occurrence);

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});
