<?php

namespace AtxDigital\Ticketing\Events;

use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\CheckIn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendeeCheckedIn
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Attendee $attendee,
        public CheckIn $checkIn,
    ) {}
}
