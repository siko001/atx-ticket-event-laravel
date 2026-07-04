<?php

namespace AtxDigital\Ticketing\Pricing;

/**
 * The outcome of a single rule that applied: the new unit price plus a
 * human-readable label for the order item's pricing snapshot.
 */
final readonly class RuleApplication
{
    public function __construct(
        public int $unitPrice,
        public string $label,
        public bool $isDiscountCode = false,
    ) {}
}
