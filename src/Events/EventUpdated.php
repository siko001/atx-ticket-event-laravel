<?php

namespace AtxDigital\Ticketing\Events;

use AtxDigital\Ticketing\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Event $event) {}
}
