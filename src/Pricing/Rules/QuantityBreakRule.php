<?php

namespace AtxDigital\Ticketing\Pricing\Rules;

use AtxDigital\Ticketing\Contracts\PricingRuleContract;
use AtxDigital\Ticketing\Pricing\PricingContext;
use AtxDigital\Ticketing\Pricing\RuleApplication;

/**
 * Config: {
 *   "min_quantity": int,
 *   "value_type": "percentage" | "fixed",
 *   "value": int (percent 0-100, or minor units off per unit)
 * }
 */
class QuantityBreakRule implements PricingRuleContract
{
    public function apply(int $unitPrice, array $config, PricingContext $context): ?RuleApplication
    {
        $minQuantity = (int) ($config['min_quantity'] ?? 0);

        if ($minQuantity < 1 || $context->quantity < $minQuantity) {
            return null;
        }

        $value = (int) ($config['value'] ?? 0);
        $valueType = (string) ($config['value_type'] ?? 'percentage');

        $newPrice = $valueType === 'fixed'
            ? $unitPrice - $value
            : (int) round($unitPrice * (1 - $value / 100));

        return new RuleApplication(
            unitPrice: max(0, $newPrice),
            label: sprintf('Quantity break (%d+)', $minQuantity),
        );
    }
}
