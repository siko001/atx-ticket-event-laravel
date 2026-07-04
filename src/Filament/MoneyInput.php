<?php

namespace AtxDigital\Ticketing\Filament;

use Filament\Forms\Components\TextInput;

/**
 * A text input that shows/accepts money in major units (5.50) while the
 * database keeps integers in minor units (550). Use for any package money
 * field so admins never think in cents.
 */
final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->step('0.01')
            ->minValue(0)
            ->formatStateUsing(fn ($state): ?string => $state === null || $state === ''
                ? null
                : number_format(((int) $state) / 100, 2, '.', ''))
            ->dehydrateStateUsing(fn ($state): ?int => $state === null || $state === ''
                ? null
                : (int) round(((float) $state) * 100));
    }
}
