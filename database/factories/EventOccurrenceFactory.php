<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventOccurrenceFactory extends Factory
{
    protected $model = EventOccurrence::class;

    public function definition(): array
    {
        $start = now()->addMonth()->setTime(18, 0);

        return [
            'event_id' => Event::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'status' => OccurrenceStatus::Scheduled,
        ];
    }
}
