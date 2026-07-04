<?php

namespace AtxDigital\Ticketing\WordPress;

use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tests a Laravel → WordPress connection by sending a signed
 * "connection.test" envelope to its webhook endpoint. The WP plugin
 * verifies the signature and acknowledges unknown types with 200, so:
 * 2xx = reachable + secret matches, 401 = secret mismatch.
 */
class ConnectionTester
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function test(Connection $connection): array
    {
        if ($connection->webhook_url === '' || $connection->webhook_secret === '') {
            return $this->record($connection, false, 'Set the webhook URL and shared secret first.');
        }

        $body = (string) json_encode([
            'type' => 'connection.test',
            'sent_at' => now()->toIso8601String(),
            'event' => ['ping' => true],
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = (string) now()->getTimestamp();

        try {
            $response = Http::withHeaders([
                'X-Atx-Ticketing-Timestamp' => $timestamp,
                'X-Atx-Ticketing-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, (string) $connection->webhook_secret),
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->withBody($body, 'application/json')
                ->post($connection->webhook_url);
        } catch (Throwable $exception) {
            return $this->record($connection, false, 'Could not reach the WordPress site: '.$exception->getMessage());
        }

        if ($response->status() === 401) {
            return $this->record($connection, false, 'Reached WordPress, but the shared secret does not match the one saved in that site\'s plugin settings.');
        }

        if ($response->successful()) {
            return $this->record($connection, true, 'Connected — WordPress accepted the signed request.');
        }

        return $this->record($connection, false, "WordPress responded with HTTP {$response->status()} — check the webhook URL (it should end in /wp-json/atx-ticketing/v1/webhook).");
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function record(Connection $connection, bool $ok, string $message): array
    {
        $result = [
            'ok' => $ok,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ];

        if ($connection->exists) {
            $connection->forceFill(['last_test' => $result])->save();
        } else {
            Settings::set('wp.last_test', $result);
        }

        return ['ok' => $ok, 'message' => $message];
    }
}
