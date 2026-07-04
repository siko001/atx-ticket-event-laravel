<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Models\EventOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CheckInStatsController extends Controller
{
    public function __invoke(string $occurrence): JsonResponse
    {
        /** @var EventOccurrence $occurrenceModel */
        $occurrenceModel = ticketing_model('event_occurrence')::query()->findOrFail((int) $occurrence);

        $paid = $occurrenceModel->attendeeQuery([OrderStatus::Paid]);

        return response()->json([
            'occurrence_id' => $occurrenceModel->getKey(),
            'total' => (clone $paid)->count(),
            'checked_in' => (clone $paid)->whereNotNull('checked_in_at')->count(),
        ]);
    }
}
