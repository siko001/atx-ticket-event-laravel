<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Payments\StripeKeys;
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
        // Several Stripe accounts may feed this endpoint (per-connection keys),
        // so try every known signing secret until one verifies.
        $event = null;
        $lastError = 'no webhook signing secrets configured';

        foreach (StripeKeys::webhookSecretCandidates() as $secret) {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    (string) $request->header('Stripe-Signature'),
                    $secret,
                );

                break;
            } catch (SignatureVerificationException|UnexpectedValueException $e) {
                $lastError = $e->getMessage();
            }
        }

        if ($event === null) {
            Log::warning('Rejected Stripe webhook with invalid signature or payload.', [
                'exception' => $lastError,
            ]);

            return response('Invalid webhook.', 400);
        }

        $handler->handle($event);

        return response()->noContent();
    }
}
