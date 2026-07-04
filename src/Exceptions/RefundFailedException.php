<?php

namespace AtxDigital\Ticketing\Exceptions;

use Throwable;

class RefundFailedException extends TicketingException
{
    public static function wrap(Throwable $previous): self
    {
        return new self('The refund could not be processed: '.$previous->getMessage(), 0, $previous);
    }
}
