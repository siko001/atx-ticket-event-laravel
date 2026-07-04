<?php

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Filament\Pages\CheckInScanner;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;

it('labels dates with the event name, flags today, and caps upcoming at 3 + more', function () {
    $event = Event::factory()->create([
        'title' => 'ATX Monthly Tech Meetup',
        'status' => EventStatus::Published,
        'timezone' => 'UTC',
    ]);

    EventOccurrence::factory()->for($event, 'event')->create([
        'starts_at' => now()->setTime(20, 30),
        'status' => OccurrenceStatus::Scheduled,
    ]);

    // Six future dates — only the next 3 should be shown, 3 hidden behind "more".
    foreach (range(1, 6) as $months) {
        EventOccurrence::factory()->for($event, 'event')->create([
            'starts_at' => now()->addMonths($months)->setTime(20, 30),
            'status' => OccurrenceStatus::Scheduled,
        ]);
    }

    $data = (new CheckInScanner)->scannerEvents();

    expect($data)->toHaveCount(1);
    $group = $data[0];

    expect($group['title'])->toBe('ATX Monthly Tech Meetup')
        ->and($group['occurrences'])->toHaveCount(4) // today + next 3
        ->and($group['more_count'])->toBe(3)
        ->and($group['occurrences'][0]['label'])->toContain('ATX Monthly Tech Meetup')
        ->and($group['occurrences'][0]['label'])->toContain('Today')
        ->and($group['occurrences'][0]['is_today'])->toBeTrue()
        ->and($group['occurrences'][1]['is_today'])->toBeFalse();
});
