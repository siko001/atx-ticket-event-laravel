<?php

use AtxDigital\Ticketing\Support\ModelResolver;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('ticketing_model')) {
    /**
     * Resolve a swappable ticketing model class from config('ticketing.models').
     *
     * @return class-string<Model>
     */
    function ticketing_model(string $key): string
    {
        return ModelResolver::resolve($key);
    }
}

if (! function_exists('ticketing_money')) {
    /**
     * Format an integer amount in minor units for display.
     */
    function ticketing_money(int $minorUnits, ?string $currency = null): string
    {
        $currency ??= (string) config('ticketing.currency', 'eur');

        return strtoupper($currency).' '.number_format($minorUnits / 100, 2);
    }
}
