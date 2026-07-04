<?php

namespace AtxDigital\Ticketing\Console;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Jobs\PushEventToWordPress;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Support\WpConnection;
use AtxDigital\Ticketing\WordPress\EventPayloadBuilder;
use Illuminate\Console\Command;

class PushEventsToWordPressCommand extends Command
{
    protected $signature = 'ticketing:push-events
        {--event= : Only push a single event by ID}';

    protected $description = 'Re-push published events to the configured WordPress webhook (full resync).';

    public function handle(EventPayloadBuilder $payloadBuilder): int
    {
        if (! WpConnection::configured()) {
            $this->error('No WordPress connection is configured (Connections screen or TICKETING_WP_WEBHOOK_URL).');

            return self::FAILURE;
        }

        $query = ticketing_model('event')::query()
            ->where('status', EventStatus::Published);

        if ($this->option('event') !== null) {
            $query->whereKey((int) $this->option('event'));
        }

        $count = 0;

        /** @var Event $event */
        foreach ($query->get() as $event) {
            PushEventToWordPress::dispatch('event.updated', $payloadBuilder->build($event));
            $count++;

            $this->line("  Queued push for: {$event->title}");
        }

        $this->info("Queued {$count} event push(es).");

        return self::SUCCESS;
    }
}
