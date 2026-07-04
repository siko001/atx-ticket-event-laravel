<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpeakerFactory extends Factory
{
    protected $model = Speaker::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'bio' => fake()->paragraph(),
            'organisation' => fake()->company(),
            'social_links' => ['linkedin' => 'https://linkedin.com/in/'.fake()->userName()],
        ];
    }
}
