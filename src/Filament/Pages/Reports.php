<?php

namespace AtxDigital\Ticketing\Filament\Pages;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Reports\AttendanceReport;
use AtxDigital\Ticketing\Reports\CheckInReport;
use AtxDigital\Ticketing\Reports\DiscountCodeUsageReport;
use AtxDigital\Ticketing\Reports\PaymentReconciliationReport;
use AtxDigital\Ticketing\Reports\RegistrationReport;
use AtxDigital\Ticketing\Reports\Report;
use AtxDigital\Ticketing\Reports\ReportFilters;
use AtxDigital\Ticketing\Reports\RevenueReport;
use AtxDigital\Ticketing\TicketingPlugin;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Reports extends Page
{
    protected string $view = 'ticketing::filament.pages.reports';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 8;

    public string $report = 'revenue';

    public string $eventId = '';

    public string $from = '';

    public string $until = '';

    public string $status = '';

    public static function canAccess(): bool
    {
        // Define a "ticketing.reports" gate in the host app to scope access;
        // without one, any panel user may view reports.
        return Gate::has('ticketing.reports')
            ? Gate::allows('ticketing.reports')
            : true;
    }

    /**
     * Override in a subclass to add app-specific reports.
     *
     * @return array<string, class-string<Report>>
     */
    public static function reports(): array
    {
        return [
            'revenue' => RevenueReport::class,
            'attendance' => AttendanceReport::class,
            'registrations' => RegistrationReport::class,
            'reconciliation' => PaymentReconciliationReport::class,
            'discount-codes' => DiscountCodeUsageReport::class,
            'check-ins' => CheckInReport::class,
        ];
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
     * @return array<string, string>
     */
    public function reportOptions(): array
    {
        return collect(static::reports())
            ->map(fn (string $class) => app($class)->label())
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function eventOptions(): array
    {
        return ticketing_model('event')::query()
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return $this->makeReport()->headers();
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    public function rows(): array
    {
        return $this->makeReport()->rows($this->filters());
    }

    public function export(): StreamedResponse
    {
        $report = $this->makeReport();

        $csv = Writer::createFromString();
        $csv->insertOne($report->headers());
        $csv->insertAll($report->rows($this->filters()));

        $content = $csv->toString();
        $filename = $this->report.'-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print $content,
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    protected function makeReport(): Report
    {
        $class = static::reports()[$this->report] ?? RevenueReport::class;

        return app($class);
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return [
            '' => 'Default for report',
            'pending' => 'Pending only',
            'paid' => 'Paid only',
            'refunded' => 'Refunded only',
            'cancelled' => 'Cancelled only',
        ];
    }

    protected function filters(): ReportFilters
    {
        $status = OrderStatus::tryFrom($this->status);

        return new ReportFilters(
            eventId: $this->eventId === '' ? null : (int) $this->eventId,
            from: $this->from === '' ? null : CarbonImmutable::parse($this->from),
            until: $this->until === '' ? null : CarbonImmutable::parse($this->until),
            statuses: $status === null ? [] : [$status],
        );
    }
}
