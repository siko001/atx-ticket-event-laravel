<?php

namespace AtxDigital\Ticketing\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ModelResolver
{
    /**
     * @return class-string<Model>
     */
    public static function resolve(string $key): string
    {
        $class = config("ticketing.models.{$key}");

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException(
                "No ticketing model registered for [{$key}]. Check config('ticketing.models')."
            );
        }

        return $class;
    }
}
