<?php

namespace AtxDigital\Ticketing\Pricing;

use AtxDigital\Ticketing\Enums\DiscountType;
use AtxDigital\Ticketing\Models\DiscountCode;

/**
 * Snapshot of a discount code, resolved from the database by the caller
 * before pricing runs, keeping the engine itself DB-free.
 */
final readonly class DiscountCodeData
{
    /**
     * @param  int  $value  Percent (0-100) for percentage codes, minor units for fixed codes.
     * @param  array<int, int>|null  $ticketTypeIds  Null = applies to every ticket type.
     */
    public function __construct(
        public string $code,
        public DiscountType $type,
        public int $value,
        public ?array $ticketTypeIds = null,
    ) {}

    public static function fromModel(DiscountCode $code): self
    {
        return new self(
            code: $code->code,
            type: $code->type,
            value: $code->value,
            ticketTypeIds: $code->ticket_type_ids === null
                ? null
                : array_map(intval(...), $code->ticket_type_ids),
        );
    }

    public function appliesToTicketType(?int $ticketTypeId): bool
    {
        return $this->ticketTypeIds === null
            || ($ticketTypeId !== null && in_array($ticketTypeId, $this->ticketTypeIds, true));
    }
}
