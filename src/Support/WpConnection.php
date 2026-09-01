<?php

namespace AtxDigital\Ticketing\Support;

use AtxDigital\Ticketing\Contracts\ResolvesWordPressConnections;
use AtxDigital\Ticketing\Contracts\WordPressConnectionProvider;
use AtxDigital\Ticketing\Models\Connection;
use Illuminate\Support\Collection;

/**
 * The connected WordPress site(s), resolved by the configured provider.
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
        return app(WordPressConnectionProvider::class)->targets();
    }

    /**
     * Resolve a host-provided connection reference, including inactive
     * connections needed for historical payment operations.
     */
    public static function resolve(string $reference): ?Connection
    {
        $provider = app(WordPressConnectionProvider::class);

        if ($provider instanceof ResolvesWordPressConnections) {
            return $provider->resolve($reference);
        }

        return $provider->targets()->first(
            fn (Connection $connection): bool => $connection->provider_reference === $reference,
        );
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
}
