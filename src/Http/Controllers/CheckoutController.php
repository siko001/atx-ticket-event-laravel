<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Exceptions\PaymentFailedException;
use AtxDigital\Ticketing\Http\Requests\CheckoutRequest;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Services\CheckoutService;
use AtxDigital\Ticketing\Support\WpConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, string $event, CheckoutService $checkout): JsonResponse
    {
        /** @var Event $eventModel */
        $eventModel = ticketing_model('event')::query()->findOrFail((int) $event);

        // The WP proxy signs its checkout calls, identifying which connection
        // (site) the order came through — and therefore which Stripe keys and
        // test/live mode apply. Unsigned (direct/public) calls stay allowed
        // and use the app-wide live keys.
        $timestamp = (string) $request->header('X-Atx-Ticketing-Timestamp', '');
        $signature = (string) $request->header('X-Atx-Ticketing-Signature', '');
        $connection = null;

        if ($timestamp !== '' && $signature !== '' && abs(time() - (int) $timestamp) <= 300) {
            $connection = WpConnection::matchSecret($timestamp, $signature, (string) $request->getContent());
        }

        try {
            $result = $checkout->checkout($eventModel, $request->validated(), $connection);
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
