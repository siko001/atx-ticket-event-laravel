<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'event_occurrence_id' => EventOccurrence::factory(),
            'event_id' => fn (array $attributes) => EventOccurrence::query()
                ->findOrFail($attributes['event_occurrence_id'])->event_id,
            'status' => OrderStatus::Pending,
            'currency' => 'eur',
            'purchaser_name' => fake()->name(),
            'purchaser_email' => fake()->safeEmail(),
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
