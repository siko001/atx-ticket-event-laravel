<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use AtxDigital\Ticketing\Models\RegistrationResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationResponseFactory extends Factory
{
    protected $model = RegistrationResponse::class;

    public function definition(): array
    {
        return [
            'attendee_id' => Attendee::factory(),
            'registration_question_id' => RegistrationQuestion::factory(),
            'label' => rtrim(fake()->sentence(4), '.').'?',
            'value' => fake()->word(),
        ];
    }
}
