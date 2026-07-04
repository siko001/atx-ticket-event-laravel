<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationQuestionFactory extends Factory
{
    protected $model = RegistrationQuestion::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'label' => rtrim(fake()->sentence(4), '.').'?',
            'type' => 'text',
            'is_required' => false,
        ];
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }

    public function select(array $options): static
    {
        return $this->state([
            'type' => 'select',
            'options' => $options,
        ]);
    }
}
