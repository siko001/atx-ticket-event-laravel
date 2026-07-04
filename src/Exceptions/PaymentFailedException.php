<?php

namespace AtxDigital\Ticketing\Exceptions;

use Throwable;

class PaymentFailedException extends TicketingException
{
    public static function wrap(Throwable $previous): self
    {
        return new self('The payment provider rejected the request: '.$previous->getMessage(), 0, $previous);
    }
}
