<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\Pages;

use AtxDigital\Ticketing\Filament\Resources\EventResource;
use AtxDigital\Ticketing\Models\Event;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Every event gets at least one occurrence — registrations always attach
     * to an occurrence, even for one-off events.
     */
    protected function afterCreate(): void
    {
        $state = $this->form->getRawState();

        /** @var Event $event */
        $event = $this->record;

        if (filled($state['first_starts_at'] ?? null)) {
            $event->occurrences()->create([
                'starts_at' => $state['first_starts_at'],
                'ends_at' => $state['first_ends_at'] ?? null,
            ]);
        }
    }
}
