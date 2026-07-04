<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\CheckIn;

final readonly class CheckInResult
{
    public const CHECKED_IN = 'checked_in';

    public const ALREADY_CHECKED_IN = 'already_checked_in';

    public const INVALID = 'invalid';

    public const NOT_PAID = 'not_paid';

    public const EXPIRED = 'expired';

    private function __construct(
        public string $status,
        public ?Attendee $attendee = null,
        public ?CheckIn $checkIn = null,
    ) {}

    public static function checkedIn(Attendee $attendee, CheckIn $checkIn): self
    {
        return new self(self::CHECKED_IN, $attendee, $checkIn);
    }

    public static function alreadyCheckedIn(Attendee $attendee): self
    {
        return new self(self::ALREADY_CHECKED_IN, $attendee);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function notPaid(Attendee $attendee): self
    {
        return new self(self::NOT_PAID, $attendee);
    }

    public static function expired(Attendee $attendee): self
    {
        return new self(self::EXPIRED, $attendee);
    }
}
