<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Services\CheckInResult;
use AtxDigital\Ticketing\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckInController extends Controller
{
    public function __invoke(Request $request, string $token, CheckInService $service): JsonResponse
    {
        $result = $service->checkIn($token, $request->user()?->getAuthIdentifier(), [
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'source' => (string) $request->input('source', 'scanner'),
        ]);

        $attendeeSummary = $result->attendee === null ? null : [
            'name' => $result->attendee->name,
            'ticket_type' => $result->attendee->orderItem?->ticketType?->name,
            'event' => $result->attendee->orderItem?->order?->event?->title,
        ];

        return match ($result->status) {
            CheckInResult::CHECKED_IN => response()->json([
                'status' => $result->status,
                'message' => 'Checked in.',
                'attendee' => $attendeeSummary,
                'checked_in_at' => $result->checkIn?->checked_in_at?->toIso8601String(),
            ]),
            CheckInResult::ALREADY_CHECKED_IN => response()->json([
                'status' => $result->status,
                'message' => 'Already checked in at '.$result->attendee?->checked_in_at?->format('H:i (j M Y)').'.',
                'attendee' => $attendeeSummary,
                'checked_in_at' => $result->attendee?->checked_in_at?->toIso8601String(),
            ]),
            CheckInResult::NOT_PAID => response()->json([
                'status' => $result->status,
                'message' => 'The order for this ticket has not been paid.',
                'attendee' => $attendeeSummary,
            ], 409),
            CheckInResult::EXPIRED => response()->json([
                'status' => $result->status,
                'message' => 'This ticket has expired.',
                'attendee' => $attendeeSummary,
            ], 410),
            default => response()->json([
                'status' => CheckInResult::INVALID,
                'message' => 'Unknown ticket.',
            ], 404),
        };
    }
}
