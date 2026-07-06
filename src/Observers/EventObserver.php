<?php

namespace AtxDigital\Ticketing\Observers;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Events\EventCancelled;
use AtxDigital\Ticketing\Events\EventDeleted;
use AtxDigital\Ticketing\Events\EventPublished;
use AtxDigital\Ticketing\Events\EventUpdated;
use AtxDigital\Ticketing\Models\Event;

class EventObserver
{
    public function saving(Event $event): void
    {
        if ($event->status === EventStatus::Published && $event->published_at === null) {
            $event->published_at = now();
        }
    }

    public function created(Event $event): void
    {
        if ($event->isPublished()) {
            event(new EventPublished($event));
        }
    }

    public function updated(Event $event): void
    {
        if ($event->wasChanged('status')) {
            if ($event->status === EventStatus::Cancelled && $event->getOriginal('status') !== EventStatus::Cancelled) {
                // Cancelling the event cancels every occurrence that is still
                // scheduled, so the date list — and the WordPress mirror built
                // from it — reflect the cancellation. Already-cancelled dates
                // are left untouched.
                $event->occurrences()
                    ->where('status', OccurrenceStatus::Scheduled->value)
                    ->update(['status' => OccurrenceStatus::Cancelled->value]);
            }

            if ($event->isPublished()) {
                event(new EventPublished($event));

                return;
            }

            if ($event->status === EventStatus::Cancelled && $event->getOriginal('status') === EventStatus::Published) {
                event(new EventCancelled($event));

                return;
            }
        }

        if ($event->isPublished() && $event->wasChanged()) {
            event(new EventUpdated($event));
        }
    }

    public function deleted(Event $event): void
    {
        event(new EventDeleted($event));
    }
}
