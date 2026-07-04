<?php

namespace AtxDigital\Ticketing\Listeners;

use AtxDigital\Ticketing\Events\OrderPaid;
use AtxDigital\Ticketing\Jobs\GenerateTicketAssets;
use AtxDigital\Ticketing\Jobs\SendOrderConfirmation;
use Illuminate\Support\Facades\Bus;

/**
 * Chains ticket generation before the confirmation email so attachments are
 * ready when the mail is built. Runs on the queue, keeping webhook responses
 * fast.
 */
class QueueTicketFulfillment
{
    public function handle(OrderPaid $event): void
    {
        Bus::chain([
            new GenerateTicketAssets($event->order),
            new SendOrderConfirmation($event->order),
        ])->dispatch();
    }
}
