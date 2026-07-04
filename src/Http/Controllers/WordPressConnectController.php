<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Support\ActivityLogger;
use AtxDigital\Ticketing\Support\Settings;
use AtxDigital\Ticketing\Support\WpConnection;
use AtxDigital\Ticketing\WordPress\EventPayloadBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints called BY the WordPress plugin (WP → Laravel), authenticated with
 * the same HMAC scheme the outgoing webhooks use, applied to the request's
 * (empty) body: sha256=hex hmac_sha256(secret, "{timestamp}.{raw body}").
 *
 * - GET wp/ping    → connection test for the plugin's "Test connection" button.
 * - GET wp/events  → full export of published events so the plugin's
 *                    "Sync now" button can pull the mirror up to date.
 */
class WordPressConnectController
{
    protected ?Connection $matched = null;

    public function ping(Request $request): JsonResponse
    {
        if (($failure = $this->verifySignature($request)) !== null) {
            return $failure;
        }

        $this->recordPull(['action' => 'ping']);

        ActivityLogger::sync('WordPress tested the connection (ping OK).');

        return response()->json([
            'ok' => true,
            'test_mode' => (bool) ($this->matched->is_test_mode ?? false),
            'app' => (string) config('app.name'),
            'time' => now()->toIso8601String(),
            'published_events' => ticketing_model('event')::query()
                ->where('status', EventStatus::Published)
                ->count(),
        ]);
    }

    public function events(Request $request, EventPayloadBuilder $payloadBuilder): JsonResponse
    {
        if (($failure = $this->verifySignature($request)) !== null) {
            return $failure;
        }

        /** @var Collection<int, Event> $events */
        $events = ticketing_model('event')::query()
            ->where('status', EventStatus::Published)
            ->orderBy('id')
            ->get();

        $this->recordPull(['action' => 'sync', 'events' => $events->count()]);

        ActivityLogger::sync("WordPress pulled a full sync ({$events->count()} event(s)).");

        return response()->json([
            'test_mode' => (bool) ($this->matched->is_test_mode ?? false),
            'events' => $events
                ->map(fn (Event $event): array => $payloadBuilder->build($event))
                ->values()
                ->all(),
        ]);
    }

    /**
     * WP → Laravel: the site toggled test mode in its own dashboard.
     * saveQuietly avoids the model event echoing the change straight back.
     */
    public function mode(Request $request): JsonResponse
    {
        if (($failure = $this->verifySignature($request)) !== null) {
            return $failure;
        }

        $testMode = filter_var($request->input('test_mode'), FILTER_VALIDATE_BOOLEAN);

        if ($this->matched !== null && $this->matched->exists) {
            $this->matched->forceFill(['is_test_mode' => $testMode])->saveQuietly();

            Settings::set('wp.last_pull', ['action' => 'mode', 'at' => now()->toIso8601String()]);

            ActivityLogger::sync(
                "\"{$this->matched->name}\" switched itself to ".($testMode ? 'TEST' : 'live').' mode from the WP dashboard.',
                ['connection' => $this->matched->name, 'test_mode' => $testMode],
                $testMode ? 'warning' : 'info',
            );
        }

        return response()->json(['ok' => true, 'test_mode' => $testMode]);
    }

    /**
     * Returns an error response when the signature is missing/invalid, null
     * when the request is authentic.
     */
    protected function verifySignature(Request $request): ?JsonResponse
    {
        if (WpConnection::secrets() === []) {
            return response()->json(['error' => 'No webhook shared secret is configured on the Laravel side (Connections screen or TICKETING_WP_WEBHOOK_SECRET).'], 503);
        }

        $timestamp = (string) $request->header('X-Atx-Ticketing-Timestamp', '');
        $signature = (string) $request->header('X-Atx-Ticketing-Signature', '');

        if ($timestamp === '' || $signature === '' || preg_match('/^\d+$/', $timestamp) !== 1) {
            return response()->json(['error' => 'Missing signature headers.'], 401);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['error' => 'Signature timestamp outside tolerance.'], 401);
        }

        $this->matched = WpConnection::matchSecret($timestamp, $signature, (string) $request->getContent());

        if ($this->matched === null) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        return null;
    }

    /**
     * Records which connection pulled, on its own row when it has one.
     *
     * @param  array<string, mixed>  $pull
     */
    protected function recordPull(array $pull): void
    {
        $pull['at'] = now()->toIso8601String();

        if ($this->matched !== null && $this->matched->exists) {
            $this->matched->forceFill(['last_pull' => $pull])->save();

            return;
        }

        Settings::set('wp.last_pull', $pull);
    }
}
