<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'venue_name' => fake()->company(),
            'venue_address' => fake()->address(),
            'status' => EventStatus::Draft,
            'timezone' => 'UTC',
            'is_recurring' => false,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => EventStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function recurring(string $rule = 'FREQ=WEEKLY;COUNT=10'): static
    {
        return $this->state([
            'is_recurring' => true,
            'recurrence_rule' => $rule,
        ]);
    }
}
