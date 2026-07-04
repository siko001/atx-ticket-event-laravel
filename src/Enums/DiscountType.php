<?php

namespace AtxDigital\Ticketing\Enums;

use Filament\Support\Contracts\HasLabel;

enum DiscountType: string implements HasLabel
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Apply this discount to an amount in minor units.
     */
    public function applyTo(int $amount, int $value): int
    {
        $discounted = match ($this) {
            self::Percentage => (int) round($amount * (1 - $value / 100)),
            self::Fixed => $amount - $value,
        };

        return max(0, $discounted);
    }
}
