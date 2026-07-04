<?php

namespace AtxDigital\Ticketing\Jobs;

use AtxDigital\Ticketing\WordPress\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushEventToWordPress implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 300, 900];

    /**
     * The payload is snapshotted at dispatch time so deletes (where the model
     * is gone by the time the job runs) and rapid successive edits stay
     * consistent.
     *
     * @param  array<string, mixed>  $eventPayload
     */
    public function __construct(
        public string $type,
        public array $eventPayload,
        public ?int $connectionId = null,
    ) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $dispatcher->send($this->type, $this->eventPayload, $this->connectionId);
    }
}
