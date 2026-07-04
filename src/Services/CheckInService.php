<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Events\AttendeeCheckedIn;
use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\CheckIn;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    /**
     * Validate a scanned token and record the check-in. Idempotent: scanning
     * an already-checked-in ticket returns ALREADY_CHECKED_IN (with the
     * original timestamp on the attendee) instead of erroring or duplicating.
     *
     * @param  array<string, mixed>  $metadata  Device/source info stored on the CheckIn row.
     */
    public function checkIn(string $token, int|string|null $userId = null, array $metadata = []): CheckInResult
    {
        $result = DB::transaction(function () use ($token, $userId, $metadata) {
            /** @var Attendee|null $attendee */
            $attendee = ticketing_model('attendee')::query()
                ->where('checkin_token', $token)
                ->lockForUpdate()
                ->first();

            if ($attendee === null) {
                return CheckInResult::invalid();
            }

            $order = $attendee->orderItem?->order;

            if ($order === null || ! $order->isPaid()) {
                return CheckInResult::notPaid($attendee);
            }

            $ttlDays = config('ticketing.checkin.token_ttl_days');

            if ($ttlDays !== null && $order->paid_at !== null && $order->paid_at->copy()->addDays((int) $ttlDays)->isPast()) {
                return CheckInResult::expired($attendee);
            }

            if ($attendee->checked_in_at !== null) {
                return CheckInResult::alreadyCheckedIn($attendee);
            }

            /** @var CheckIn $checkIn */
            $checkIn = $attendee->checkIns()->create([
                'checked_in_at' => now(),
                'checked_in_by' => is_numeric($userId) ? (int) $userId : null,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);

            $attendee->forceFill(['checked_in_at' => now()])->save();

            return CheckInResult::checkedIn($attendee, $checkIn);
        });

        if ($result->status === CheckInResult::CHECKED_IN && $result->attendee !== null && $result->checkIn !== null) {
            event(new AttendeeCheckedIn($result->attendee, $result->checkIn));
        }

        return $result;
    }
}
