<?php

namespace AtxDigital\Ticketing\Reports;

use AtxDigital\Ticketing\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class ReportFilters
{
    /**
     * @param  array<int, OrderStatus>  $statuses  Empty = the report's own default.
     */
    public function __construct(
        public ?int $eventId = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $until = null,
        public array $statuses = [],
    ) {}

    /**
     * The order statuses to include, falling back to the report's default.
     *
     * @param  array<int, OrderStatus>  $default
     * @return array<int, OrderStatus>
     */
    public function statusesOr(array $default): array
    {
        return $this->statuses === [] ? $default : $this->statuses;
    }

    /**
     * Apply the date range to a query column.
     */
    public function applyDates(Builder|Relation $query, string $column): Builder|Relation
    {
        if ($this->from !== null) {
            $query->where($column, '>=', $this->from->startOfDay());
        }

        if ($this->until !== null) {
            $query->where($column, '<=', $this->until->endOfDay());
        }

        return $query;
    }
}
