<?php

use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Services\OccurrenceMaterializer;

it('expands a weekly RRULE into future occurrences with the template duration', function () {
    $event = Event::factory()->published()->recurring('FREQ=WEEKLY;COUNT=6')->create([
        'timezone' => 'Europe/Malta',
    ]);

    $start = now()->addDays(3)->setTime(18, 0)->startOfMinute();

    EventOccurrence::factory()->for($event, 'event')->create([
        'starts_at' => $start,
        'ends_at' => $start->copy()->addHours(2),
    ]);

    $created = app(OccurrenceMaterializer::class)->materialize($event);

    // COUNT=6 including the template, which already exists.
    expect($created)->toBe(5)
        ->and($event->occurrences()->count())->toBe(6);

    $second = $event->occurrences()->orderBy('starts_at')->skip(1)->first();

    expect($second->starts_at->toIso8601String())->toBe($start->copy()->addWeek()->toIso8601String())
        ->and((int) $second->starts_at->diffInSeconds($second->ends_at))->toBe(7200);
});

it('is idempotent across repeated runs', function () {
    $event = Event::factory()->published()->recurring('FREQ=WEEKLY;COUNT=4')->create();

    EventOccurrence::factory()->for($event, 'event')->create([
        'starts_at' => now()->addDays(2)->setTime(10, 0)->startOfMinute(),
    ]);

    $materializer = app(OccurrenceMaterializer::class);

    $materializer->materialize($event);
    $secondRun = $materializer->materialize($event);

    expect($secondRun)->toBe(0)
        ->and($event->occurrences()->count())->toBe(4);
});

it('respects the rolling window', function () {
    $event = Event::factory()->published()->recurring('FREQ=MONTHLY')->create();

    EventOccurrence::factory()->for($event, 'event')->create([
        'starts_at' => now()->addDays(10)->setTime(9, 0)->startOfMinute(),
    ]);

    app(OccurrenceMaterializer::class)->materialize($event, now()->addMonths(3));

    expect($event->occurrences()->count())->toBeLessThanOrEqual(4)
        ->and($event->occurrences()->count())->toBeGreaterThanOrEqual(3);
});

it('does nothing for non-recurring events', function () {
    $event = Event::factory()->published()->create();
    EventOccurrence::factory()->for($event, 'event')->create();

    expect(app(OccurrenceMaterializer::class)->materialize($event))->toBe(0)
        ->and($event->occurrences()->count())->toBe(1);
});
