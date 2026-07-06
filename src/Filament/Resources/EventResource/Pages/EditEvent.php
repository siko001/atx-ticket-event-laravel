<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\Pages;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Filament\Resources\EventResource;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Collection;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    /**
     * Whether the pending status change should cascade to the event's dates.
     * Set from the confirmation modal, applied on save. Reset on every status
     * change so a dismissed modal defaults to "event only".
     */
    public string $cascadeScope = 'event';

    /**
     * The status the admin just selected (unsaved), used to build the modal
     * before the record itself is updated.
     */
    public ?string $pendingStatus = null;

    /**
     * Set when a save actually cascaded to occurrences, so we can reload the
     * page afterwards to show fresh occurrence data.
     */
    protected bool $cascadeApplied = false;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The record as a typed Event (Filament types $record loosely).
     */
    private function event(): Event
    {
        /** @var Event $record */
        $record = $this->getRecord();

        return $record;
    }

    /**
     * Called from the status Select the moment it changes. If the new status
     * cascades and there are eligible dates, open the confirmation modal.
     */
    public function onStatusChanged(mixed $state): void
    {
        $this->cascadeScope = 'event';

        $status = $state instanceof EventStatus ? $state : EventStatus::tryFrom((string) $state);
        $target = $status ? $this->occurrenceStatusFor($status) : null;

        if ($target === null || $this->eligibleOccurrences($target)->isEmpty()) {
            return;
        }

        $this->pendingStatus = $status->value;
        $this->mountAction('confirmCascade');
    }

    public function confirmCascadeAction(): Action
    {
        $status = $this->pendingStatus ? EventStatus::from($this->pendingStatus) : $this->event()->status;
        $target = $this->occurrenceStatusFor($status);
        $count = $target ? $this->eligibleOccurrences($target)->count() : 0;

        $note = $target === OccurrenceStatus::Cancelled
            ? ' Dates already in the past are left as they are.'
            : '';

        return Action::make('confirmCascade')
            ->modalHeading("Change status to “{$status->getLabel()}”")
            ->modalIcon('heroicon-o-calendar-days')
            ->modalIconColor($status->getColor())
            ->modalDescription("Apply “{$status->getLabel()}” to the event's dates too?{$note} Changes are saved when you save the event.")
            ->schema([
                Radio::make('scope')
                    ->hiddenLabel()
                    ->options([
                        'event' => 'Event only — leave the dates unchanged',
                        'all' => "Apply to the event and its {$count} date(s)",
                    ])
                    ->default('all')
                    ->required(),
            ])
            ->modalSubmitActionLabel('OK')
            ->action(function (array $data) use ($status, $count): void {
                $this->cascadeScope = $data['scope'] ?? 'event';

                if ($this->cascadeScope === 'all') {
                    Notification::make()
                        ->title('Save to apply')
                        ->body("Save the event to set its {$count} date(s) to “{$status->getLabel()}”.")
                        ->info()
                        ->send();
                }
            });
    }

    /**
     * Apply the cascade (if chosen) together with the saved event, so the
     * event status and its dates always change in the same step.
     */
    protected function afterSave(): void
    {
        if ($this->cascadeScope !== 'all' || ! $this->event()->wasChanged('status')) {
            return;
        }

        $target = $this->occurrenceStatusFor($this->event()->status);

        if ($target === null) {
            return;
        }

        $ids = $this->eligibleOccurrences($target)->modelKeys();

        if ($ids === []) {
            return;
        }

        EventOccurrence::query()->whereKey($ids)->update(['status' => $target->value]);
        $this->cascadeApplied = true;

        Notification::make()
            ->title('Dates updated')
            ->body(count($ids)." date(s) set to “{$target->getLabel()}”.")
            ->success()
            ->send();
    }

    /**
     * After a cascade, reload the edit page so the occurrence data (relation
     * manager, etc.) reflects the just-updated statuses instead of stale rows.
     */
    protected function getRedirectUrl(): ?string
    {
        return $this->cascadeApplied
            ? static::getResource()::getUrl('edit', ['record' => $this->getRecord()])
            : null;
    }

    /**
     * Dates the cascade would actually change: any occurrence not already at
     * the target status. The one exception is cancelling — it never touches a
     * date already in the past (a finished date stays as it was).
     *
     * @return Collection<int, EventOccurrence>
     */
    private function eligibleOccurrences(OccurrenceStatus $target): Collection
    {
        return EventOccurrence::query()
            ->where('event_id', $this->event()->getKey())
            ->get()
            ->filter(function (EventOccurrence $occurrence) use ($target): bool {
                if ($target === OccurrenceStatus::Cancelled && $occurrence->isPast()) {
                    return false;
                }

                return $occurrence->status !== $target;
            });
    }

    /**
     * The occurrence status an event status cascades to, or null if the event
     * status has no occurrence equivalent (e.g. Draft).
     */
    private function occurrenceStatusFor(EventStatus $status): ?OccurrenceStatus
    {
        return match ($status) {
            EventStatus::Cancelled => OccurrenceStatus::Cancelled,
            EventStatus::Past => OccurrenceStatus::Past,
            EventStatus::Published => OccurrenceStatus::Scheduled,
            default => null,
        };
    }
}
