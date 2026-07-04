<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;

class SponsorFactory extends Factory
{
    protected $model = Sponsor::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'url' => fake()->url(),
            'tier' => fake()->randomElement(['gold', 'silver', 'bronze']),
        ];
    }
}
