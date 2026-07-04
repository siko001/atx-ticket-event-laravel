<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\PricingRule;
use AtxDigital\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'ticket_type_id' => TicketType::factory(),
            'type' => 'early_bird',
            'config' => [
                'starts_at' => now()->subMonth()->toIso8601String(),
                'ends_at' => now()->addMonth()->toIso8601String(),
                'value_type' => 'percentage',
                'value' => 20,
            ],
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
