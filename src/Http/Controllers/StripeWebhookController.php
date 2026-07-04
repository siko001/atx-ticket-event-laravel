<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Payments\StripeWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookHandler $handler): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('ticketing.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            Log::warning('Rejected Stripe webhook with invalid signature or payload.', [
                'exception' => $e->getMessage(),
            ]);

            return response('Invalid webhook.', 400);
        }

        $handler->handle($event);

        return response()->noContent();
    }
}
