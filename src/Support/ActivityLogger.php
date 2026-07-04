<?php

namespace AtxDigital\Ticketing\Support;

use AtxDigital\Ticketing\Models\ActivityLog;
use Throwable;

/**
 * Durable activity log (System → Logs): "sync" for WordPress traffic,
 * "order" for purchases. Fails soft when the table is missing so logging
 * never breaks checkouts or webhooks.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function log(string $channel, string $message, array $context = [], string $level = 'info'): void
    {
        try {
            ActivityLog::query()->create([
                'channel' => $channel,
                'level' => $level,
                'message' => mb_substr($message, 0, 500),
                'context' => $context ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never let logging break the actual work.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function sync(string $message, array $context = [], string $level = 'info'): void
    {
        self::log('sync', $message, $context, $level);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function order(string $message, array $context = [], string $level = 'info'): void
    {
        self::log('order', $message, $context, $level);
    }
}
