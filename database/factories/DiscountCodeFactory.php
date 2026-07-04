<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Enums\DiscountType;
use AtxDigital\Ticketing\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DiscountCodeFactory extends Factory
{
    protected $model = DiscountCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'type' => DiscountType::Percentage,
            'value' => 10,
        ];
    }

    public function fixed(int $minorUnits): static
    {
        return $this->state([
            'type' => DiscountType::Fixed,
            'value' => $minorUnits,
        ]);
    }
}
