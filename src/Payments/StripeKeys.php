<?php

namespace AtxDigital\Ticketing\Payments;

use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Models\Order;
use Throwable;

/**
 * Resolves which Stripe keys an order uses. Two-level cascade:
 *
 *   1. The order's connection may override either pair (Connections screen).
 *   2. Otherwise the app-wide .env pair applies:
 *      live → STRIPE_SECRET / STRIPE_WEBHOOK_SECRET
 *      test → STRIPE_TEST_SECRET / STRIPE_TEST_WEBHOOK_SECRET
 *
 * Test vs live follows the order's is_test flag, snapshotted at purchase
 * time — so refunds always hit the same Stripe account/mode that took the
 * payment, even if the connection's toggle changed since.
 */
class StripeKeys
{
    public static function secretForOrder(Order $order): string
    {
        $connection = $order->connection;

        if ($order->is_test) {
            return (string) ($connection?->stripe_test_secret ?: config('ticketing.stripe.test_secret'));
        }

        return (string) ($connection?->stripe_live_secret ?: config('ticketing.stripe.secret'));
    }

    /**
     * Every webhook signing secret that incoming Stripe events may carry —
     * the two .env pairs plus every connection override. Verification tries
     * each until one matches (multiple Stripe accounts = multiple secrets).
     *
     * @return list<string>
     */
    public static function webhookSecretCandidates(): array
    {
        $candidates = [
            (string) config('ticketing.stripe.webhook_secret'),
            (string) config('ticketing.stripe.test_webhook_secret'),
        ];

        try {
            foreach (Connection::query()->get() as $connection) {
                $candidates[] = (string) $connection->stripe_live_webhook_secret;
                $candidates[] = (string) $connection->stripe_test_webhook_secret;
            }
        } catch (Throwable) {
            // Table missing — .env candidates only.
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Human label for error messages: where should the missing key be set?
     */
    public static function missingKeyHint(Order $order): string
    {
        $mode = $order->is_test ? 'test' : 'live';
        $env = $order->is_test ? 'STRIPE_TEST_SECRET' : 'STRIPE_SECRET';

        return $order->connection !== null
            ? "No Stripe {$mode} key available for connection \"{$order->connection->name}\" — set one on the Connections screen or {$env} in .env."
            : "Stripe is not configured — set {$env} in .env.";
    }
}
