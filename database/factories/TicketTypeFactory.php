<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'General admission',
            'base_price' => 5000,
            'currency' => 'eur',
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(['base_price' => 0]);
    }
}
