<?php

namespace AtxDigital\Ticketing\Reports;

abstract class Report
{
    abstract public function label(): string;

    /**
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * @return list<list<string|int|float|null>>
     */
    abstract public function rows(ReportFilters $filters): array;
}
