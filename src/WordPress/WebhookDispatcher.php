<?php

namespace AtxDigital\Ticketing\WordPress;

use AtxDigital\Ticketing\Exceptions\TicketingException;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Support\ActivityLogger;
use AtxDigital\Ticketing\Support\Settings;
use AtxDigital\Ticketing\Support\WpConnection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends signed event payloads to every active WordPress connection.
 *
 * Signature scheme (see ARCHITECTURE.md):
 *   X-Atx-Ticketing-Timestamp: <unix timestamp>
 *   X-Atx-Ticketing-Signature: sha256=<hex hmac_sha256(secret, "{timestamp}.{raw body}")>
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $eventPayload
     *
     * @throws TicketingException when any receiver responds with an error (so queued jobs retry).
     */
    public function send(string $type, array $eventPayload, ?int $connectionId = null): void
    {
        $targets = WpConnection::targets()
            ->filter(fn (Connection $connection): bool => $connection->webhook_url !== '')
            ->when($connectionId !== null, fn ($connections) => $connections->where('id', $connectionId));

        $failures = [];

        foreach ($targets as $connection) {
            if (! $this->sendTo($connection, $type, $eventPayload)) {
                $failures[] = $connection->name;
            }
        }

        if ($failures !== []) {
            throw new TicketingException(
                "WordPress webhook ({$type}) failed for connection(s): ".implode(', ', $failures).'.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    protected function sendTo(Connection $connection, string $type, array $eventPayload): bool
    {
        $body = (string) json_encode([
            'type' => $type,
            'sent_at' => now()->toIso8601String(),
            'event' => $eventPayload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $connection->webhook_secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Atx-Ticketing-Timestamp' => $timestamp,
                'X-Atx-Ticketing-Signature' => 'sha256='.$signature,
            ])
                ->timeout(15)
                ->withBody($body, 'application/json')
                ->post($connection->webhook_url);

            $ok = $response->successful();
            $status = $response->status();
        } catch (Throwable) {
            $ok = false;
            $status = 0;
        }

        ActivityLogger::sync(
            $ok
                ? "Pushed {$type} to \"{$connection->name}\" (HTTP {$status})."
                : "Push of {$type} to \"{$connection->name}\" failed (HTTP {$status}).",
            [
                'type' => $type,
                'status' => $status,
                'connection' => $connection->name,
                'event_id' => $eventPayload['id'] ?? null,
            ],
            $ok ? 'info' : 'error',
        );

        $result = [
            'ok' => $ok,
            'type' => $type,
            'status' => $status,
            'at' => now()->toIso8601String(),
        ];

        if ($connection->exists) {
            $connection->forceFill(['last_push' => $result])->save();
        } else {
            Settings::set('wp.last_push', $result);
        }

        return $ok;
    }
}
