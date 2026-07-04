<?php

namespace AtxDigital\Ticketing\Console;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Services\OccurrenceMaterializer;
use Illuminate\Console\Command;

class MaterializeOccurrencesCommand extends Command
{
    protected $signature = 'ticketing:materialize-occurrences
        {--event= : Only materialize a single event by ID}
        {--months= : Override the rolling window length in months}';

    protected $description = 'Expand recurring events\' RRULEs into concrete occurrences on a rolling window.';

    public function handle(OccurrenceMaterializer $materializer): int
    {
        $months = $this->option('months') !== null
            ? (int) $this->option('months')
            : (int) config('ticketing.recurrence.window_months', 12);

        $query = ticketing_model('event')::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_rule')
            ->where('status', '!=', EventStatus::Cancelled);

        if ($this->option('event') !== null) {
            $query->whereKey((int) $this->option('event'));
        }

        $total = 0;

        /** @var Event $event */
        foreach ($query->get() as $event) {
            $created = $materializer->materialize($event, now()->addMonths($months));
            $total += $created;

            $this->line("  {$event->title}: {$created} occurrence(s) created.");
        }

        $this->info("Done. {$total} occurrence(s) materialized.");

        return self::SUCCESS;
    }
}
