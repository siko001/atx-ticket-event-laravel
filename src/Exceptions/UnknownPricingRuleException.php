<?php

namespace AtxDigital\Ticketing\Exceptions;

class UnknownPricingRuleException extends TicketingException
{
    public static function forType(string $type): self
    {
        return new self(
            "No pricing rule registered for type [{$type}]. Register it in config('ticketing.pricing_rules')."
        );
    }
}
