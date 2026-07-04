<?php

namespace AtxDigital\Ticketing\Filament\Pages;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\TicketingPlugin;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CheckInScanner extends Page
{
    protected string $view = 'ticketing::filament.pages.check-in-scanner';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $title = 'Check-in scanner';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return Gate::allows('ticketing.checkin');
    }

    public static function getNavigationGroup(): ?string
    {
        try {
            return TicketingPlugin::get()->getNavigationGroup();
        } catch (Throwable) {
            return 'Ticketing';
        }
    }

    /**
     * Published events with upcoming (or very recent) dates, for the
     * two-step scanner picker: searchable event first, then its dates.
     * Ordered by each event's next date; today's dates are flagged so the
     * front-end can preselect them for door staff.
     *
     * @return list<array{id: int, title: string, occurrences: list<array{id: int, label: string, is_today: bool}>}>
     */
    public function scannerEvents(): array
    {
        /** @var Collection<int, EventOccurrence> $occurrences */
        $occurrences = ticketing_model('event_occurrence')::query()
            ->with('event')
            ->where('status', OccurrenceStatus::Scheduled)
            ->where('starts_at', '>=', now()->subDay())
            ->whereHas('event', fn ($query) => $query->where('status', EventStatus::Published))
            ->orderBy('starts_at')
            ->limit(2000)
            ->get();

        return $occurrences
            ->groupBy('event_id')
            ->map(function (Collection $group) {
                $event = $group->first()?->event;
                $timezone = $event->timezone ?? 'UTC';

                return [
                    'id' => (int) ($event?->getKey() ?? 0),
                    'title' => (string) ($event->title ?? 'Unknown event'),
                    'occurrences' => $group
                        ->take(50)
                        ->map(function ($occurrence) use ($timezone) {
                            $local = $occurrence->starts_at->copy()->timezone($timezone);

                            return [
                                'id' => (int) $occurrence->getKey(),
                                'label' => ($local->isToday() ? 'Today — ' : '').$local->format('D j M Y, H:i'),
                                'is_today' => $local->isToday(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
