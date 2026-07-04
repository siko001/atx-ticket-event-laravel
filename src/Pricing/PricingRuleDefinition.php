<?php

namespace AtxDigital\Ticketing\Pricing;

use AtxDigital\Ticketing\Models\PricingRule;

final readonly class PricingRuleDefinition
{
    /**
     * @param  string  $type  Discriminator mapping to a class in config('ticketing.pricing_rules').
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $type,
        public array $config = [],
        public int $priority = 0,
        public ?int $id = null,
    ) {}

    public static function fromModel(PricingRule $rule): self
    {
        return new self(
            type: $rule->type,
            config: $rule->config ?? [],
            priority: $rule->priority,
            id: $rule->getKey(),
        );
    }
}
