<?php

namespace AtxDigital\Ticketing\Listeners;

use AtxDigital\Ticketing\Events\EventCancelled;
use AtxDigital\Ticketing\Events\EventDeleted;
use AtxDigital\Ticketing\Events\EventPublished;
use AtxDigital\Ticketing\Events\EventUpdated;
use AtxDigital\Ticketing\Jobs\PushEventToWordPress;
use AtxDigital\Ticketing\Support\WpConnection;
use AtxDigital\Ticketing\WordPress\EventPayloadBuilder;

class DispatchWordPressSync
{
    public function __construct(protected EventPayloadBuilder $payloadBuilder) {}

    public function handle(EventPublished|EventUpdated|EventCancelled|EventDeleted $domainEvent): void
    {
        if (! WpConnection::configured()) {
            return;
        }

        $type = match ($domainEvent::class) {
            EventPublished::class => 'event.published',
            EventUpdated::class => 'event.updated',
            EventCancelled::class => 'event.cancelled',
            EventDeleted::class => 'event.deleted',
            default => throw new \LogicException('Unhandled domain event: '.$domainEvent::class),
        };

        $payload = $domainEvent instanceof EventDeleted
            ? ['id' => $domainEvent->event->getKey()]
            : $this->payloadBuilder->build($domainEvent->event);

        PushEventToWordPress::dispatch($type, $payload);
    }
}
