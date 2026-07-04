<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Exceptions\PaymentFailedException;
use AtxDigital\Ticketing\Http\Requests\CheckoutRequest;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, string $event, CheckoutService $checkout): JsonResponse
    {
        /** @var Event $eventModel */
        $eventModel = ticketing_model('event')::query()->findOrFail((int) $event);

        try {
            $result = $checkout->checkout($eventModel, $request->validated());
        } catch (PaymentFailedException $e) {
            report($e);

            return response()->json([
                'message' => 'Payment could not be initialised. Please try again shortly.',
            ], 502);
        }

        return response()->json([
            'order_id' => $result->order->getKey(),
            'order_number' => $result->order->order_number,
            'checkout_url' => $result->checkoutUrl,
        ], 201);
    }
}
