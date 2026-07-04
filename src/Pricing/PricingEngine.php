<?php

namespace AtxDigital\Ticketing\Pricing;

use AtxDigital\Ticketing\Contracts\PricingRuleContract;
use AtxDigital\Ticketing\Exceptions\UnknownPricingRuleException;

/**
 * Pure, stateless price resolver: evaluates the active pricing rule
 * definitions for a ticket type in priority order (lowest first) against a
 * PricingContext. No database access happens here.
 */
final class PricingEngine
{
    /**
     * @param  array<string, string>  $ruleMap  Rule type => PricingRuleContract class.
     */
    public function __construct(private readonly array $ruleMap) {}

    /**
     * @param  iterable<PricingRuleDefinition>  $rules
     */
    public function calculate(PricingContext $context, iterable $rules): PriceResult
    {
        $definitions = collect($rules)->sortBy(fn (PricingRuleDefinition $rule) => $rule->priority)->values();

        $unitPrice = max(0, $context->basePrice);
        $unitDiscount = 0;
        $applied = [];

        foreach ($definitions as $definition) {
            $rule = $this->resolveRule($definition->type);
            $application = $rule->apply($unitPrice, $definition->config, $context);

            if ($application === null) {
                continue;
            }

            $newPrice = max(0, $application->unitPrice);

            if ($application->isDiscountCode) {
                $unitDiscount += $unitPrice - $newPrice;
            }

            $applied[] = [
                'type' => $definition->type,
                'rule_id' => $definition->id,
                'label' => $application->label,
                'before' => $unitPrice,
                'after' => $newPrice,
            ];

            $unitPrice = $newPrice;
        }

        return new PriceResult($unitPrice, $unitDiscount, $applied);
    }

    private function resolveRule(string $type): PricingRuleContract
    {
        $class = $this->ruleMap[$type] ?? null;

        if ($class === null || ! is_a($class, PricingRuleContract::class, true)) {
            throw UnknownPricingRuleException::forType($type);
        }

        return new $class;
    }
}
