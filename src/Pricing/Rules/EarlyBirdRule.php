<?php

namespace AtxDigital\Ticketing\Pricing\Rules;

use AtxDigital\Ticketing\Contracts\PricingRuleContract;
use AtxDigital\Ticketing\Pricing\PricingContext;
use AtxDigital\Ticketing\Pricing\RuleApplication;
use DateTimeImmutable;

/**
 * Config: {
 *   "starts_at": ISO-8601 datetime (optional — open start),
 *   "ends_at":   ISO-8601 datetime (optional — open end),
 *   "value_type": "percentage" | "fixed",
 *   "value": int (percent 0-100, or minor units)
 * }
 */
class EarlyBirdRule implements PricingRuleContract
{
    public function apply(int $unitPrice, array $config, PricingContext $context): ?RuleApplication
    {
        $startsAt = isset($config['starts_at']) ? new DateTimeImmutable((string) $config['starts_at']) : null;
        $endsAt = isset($config['ends_at']) ? new DateTimeImmutable((string) $config['ends_at']) : null;

        if ($startsAt !== null && $context->purchasedAt < $startsAt) {
            return null;
        }

        if ($endsAt !== null && $context->purchasedAt > $endsAt) {
            return null;
        }

        $value = (int) ($config['value'] ?? 0);
        $valueType = (string) ($config['value_type'] ?? 'percentage');

        $newPrice = $valueType === 'fixed'
            ? $unitPrice - $value
            : (int) round($unitPrice * (1 - $value / 100));

        return new RuleApplication(
            unitPrice: max(0, $newPrice),
            label: sprintf(
                'Early bird (%s off)',
                $valueType === 'fixed' ? number_format($value / 100, 2) : $value.'%',
            ),
        );
    }
}
