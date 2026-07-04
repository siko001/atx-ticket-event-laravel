<?php

namespace AtxDigital\Ticketing\Contracts;

use AtxDigital\Ticketing\Pricing\PricingContext;
use AtxDigital\Ticketing\Pricing\RuleApplication;

interface PricingRuleContract
{
    /**
     * Evaluate this rule against the current unit price (minor units).
     *
     * Return a RuleApplication with the adjusted price when the rule applies,
     * or null when it does not. Implementations must be pure: no side effects,
     * no database writes, everything needed comes from $config and $context.
     *
     * @param  array<string, mixed>  $config
     */
    public function apply(int $unitPrice, array $config, PricingContext $context): ?RuleApplication;
}
