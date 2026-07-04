<?php

namespace AtxDigital\Ticketing\Events;

use AtxDigital\Ticketing\Models\Attendee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Attendee $attendee) {}
}
