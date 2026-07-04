<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\EventOccurrenceFactory;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $capacity
 * @property OccurrenceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read Collection<int, Order> $orders
 */
class EventOccurrence extends Model
{
    use HasFactory;

    protected $table = 'ticketing_event_occurrences';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'status' => OccurrenceStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('event'), 'event_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ticketing_model('order'), 'event_occurrence_id');
    }

    /**
     * A scheduled occurrence whose end (or start, if no end) is in the past.
     * Cancelled occurrences are never "past" — cancellation wins.
     */
    public function isPast(): bool
    {
        if ($this->status === OccurrenceStatus::Cancelled) {
            return false;
        }

        return ($this->ends_at ?? $this->starts_at)->isPast();
    }

    /**
     * Human status for display: Cancelled, Past, or Scheduled. "Past" is derived
     * from the clock rather than stored, so it is always current.
     */
    public function displayStatus(): string
    {
        if ($this->status === OccurrenceStatus::Cancelled) {
            return 'Cancelled';
        }

        return $this->isPast() ? 'Past' : 'Scheduled';
    }

    /**
     * Capacity override, falling back to the event's max_capacity. Null = unlimited.
     */
    public function effectiveCapacity(): ?int
    {
        return $this->capacity ?? $this->event?->max_capacity;
    }

    /**
     * Attendees for this occurrence whose orders are in the given statuses.
     */
    public function attendeeQuery(array $orderStatuses = [OrderStatus::Pending, OrderStatus::Paid]): Builder
    {
        return ticketing_model('attendee')::query()->whereHas(
            'orderItem.order',
            fn (Builder $q) => $q
                ->where('event_occurrence_id', $this->getKey())
                ->whereIn('status', $orderStatuses),
        );
    }

    /**
     * Remaining places, or null when capacity is unlimited.
     */
    public function remainingCapacity(): ?int
    {
        $capacity = $this->effectiveCapacity();

        if ($capacity === null) {
            return null;
        }

        return max(0, $capacity - $this->attendeeQuery()->count());
    }

    protected static function newFactory(): Factory
    {
        return EventOccurrenceFactory::new();
    }
}
