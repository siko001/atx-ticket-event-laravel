<?php

namespace AtxDigital\Ticketing\Support;

use AtxDigital\Ticketing\Models\Connection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The connected WordPress site(s). Rows in ticketing_connections (managed
 * on the Connections screen) are the source of truth; when none exist the
 * legacy single connection (stored settings, then the TICKETING_WP_WEBHOOK_*
 * env values) is used, so existing installs keep working unchanged.
 */
class WpConnection
{
    /**
     * All active push/pull targets. Legacy fallback rows are unsaved models
     * (no id) named "Default".
     *
     * @return Collection<int, Connection>
     */
    public static function targets(): Collection
    {
        try {
            /** @var Collection<int, Connection> $connections */
            $connections = Connection::query()->where('is_active', true)->orderBy('id')->get();
        } catch (Throwable) {
            $connections = new Collection;
        }

        if ($connections->isNotEmpty()) {
            return $connections;
        }

        $legacy = self::legacy();

        return $legacy === null ? new Collection : new Collection([$legacy]);
    }

    /**
     * Every secret that may sign incoming WP → Laravel requests.
     *
     * @return list<string>
     */
    public static function secrets(): array
    {
        return self::targets()
            ->pluck('webhook_secret')
            ->filter(fn ($secret): bool => is_string($secret) && $secret !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The connection whose secret signed the given request headers, if any.
     */
    public static function matchSecret(string $timestamp, string $signature, string $body): ?Connection
    {
        foreach (self::targets() as $connection) {
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, (string) $connection->webhook_secret);

            if (hash_equals($expected, $signature)) {
                return $connection;
            }
        }

        return null;
    }

    public static function configured(): bool
    {
        return self::targets()->isNotEmpty();
    }

    /**
     * Legacy single connection from stored settings or env, or null.
     */
    protected static function legacy(): ?Connection
    {
        $url = (string) Settings::get('wp.webhook_url', '');
        $secret = (string) Settings::get('wp.webhook_secret', '');

        if ($url === '') {
            $url = (string) config('ticketing.wp_webhook_url');
            $secret = (string) config('ticketing.wp_webhook_secret');
        }

        if ($url === '' && $secret === '') {
            return null;
        }

        $connection = new Connection;
        $connection->name = 'Default';
        $connection->webhook_url = $url;
        $connection->webhook_secret = $secret;
        $connection->is_active = true;

        return $connection;
    }
}
