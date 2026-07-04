<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendeeFactory extends Factory
{
    protected $model = Attendee::class;

    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
        ];
    }

    public function checkedIn(): static
    {
        return $this->state(['checked_in_at' => now()]);
    }
}
