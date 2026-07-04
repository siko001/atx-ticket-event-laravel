<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\CheckIn;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckInFactory extends Factory
{
    protected $model = CheckIn::class;

    public function definition(): array
    {
        return [
            'attendee_id' => Attendee::factory(),
            'checked_in_at' => now(),
        ];
    }
}
