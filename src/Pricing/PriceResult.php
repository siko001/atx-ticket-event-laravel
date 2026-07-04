<?php

namespace AtxDigital\Ticketing\Pricing;

final readonly class PriceResult
{
    /**
     * @param  int  $unitPrice  Final unit price after all rules, minor units.
     * @param  int  $unitDiscount  Portion attributable to discount codes (reported as order discount_total).
     * @param  list<array{type: string, rule_id: int|null, label: string, before: int, after: int}>  $applied
     */
    public function __construct(
        public int $unitPrice,
        public int $unitDiscount,
        public array $applied,
    ) {}

    /**
     * Unit price before discount codes — what order subtotals are built from.
     */
    public function unitPriceBeforeDiscount(): int
    {
        return $this->unitPrice + $this->unitDiscount;
    }
}
