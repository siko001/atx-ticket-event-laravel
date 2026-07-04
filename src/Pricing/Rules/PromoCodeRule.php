<?php

namespace AtxDigital\Ticketing\Pricing\Rules;

use AtxDigital\Ticketing\Contracts\PricingRuleContract;
use AtxDigital\Ticketing\Pricing\PricingContext;
use AtxDigital\Ticketing\Pricing\RuleApplication;

/**
 * Applies the discount code carried on the pricing context. The code itself
 * (window, uses, applicability) is validated and resolved to a
 * DiscountCodeData snapshot by the checkout layer before pricing runs, so
 * this rule stays pure. No config needed.
 */
class PromoCodeRule implements PricingRuleContract
{
    public function apply(int $unitPrice, array $config, PricingContext $context): ?RuleApplication
    {
        $code = $context->discountCode;

        if ($code === null || ! $code->appliesToTicketType($context->ticketTypeId)) {
            return null;
        }

        return new RuleApplication(
            unitPrice: $code->type->applyTo($unitPrice, $code->value),
            label: sprintf('Promo code %s', $code->code),
            isDiscountCode: true,
        );
    }
}
