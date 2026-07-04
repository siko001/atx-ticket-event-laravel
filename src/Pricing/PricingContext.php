<?php

namespace AtxDigital\Ticketing\Pricing;

use DateTimeImmutable;

/**
 * Immutable input to the pricing engine. Deliberately a plain value object —
 * not an Eloquent model — so pricing is unit-testable without a database.
 */
final readonly class PricingContext
{
    /**
     * @param  int  $basePrice  Ticket type base price in minor units.
     * @param  array<string, mixed>  $attendeeAttributes  Free-form attributes custom rules may inspect.
     */
    public function __construct(
        public int $basePrice,
        public string $currency,
        public int $quantity,
        public DateTimeImmutable $purchasedAt,
        public ?int $ticketTypeId = null,
        public array $attendeeAttributes = [],
        public ?DiscountCodeData $discountCode = null,
    ) {}
}
