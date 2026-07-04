<?php

namespace AtxDigital\Ticketing\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class Authorize
{
    /**
     * Policy-aware check that stays permissive when the host app has not
     * registered a policy for the model — the package must work standalone,
     * and lock down automatically once policies are bound.
     */
    public static function allows(string $ability, Model|string $model): bool
    {
        $class = is_string($model) ? $model : $model::class;

        if (Gate::getPolicyFor($class) === null) {
            return true;
        }

        return Gate::allows($ability, $model);
    }
}
