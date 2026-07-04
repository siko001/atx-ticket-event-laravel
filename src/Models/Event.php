<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\EventFactory;
use AtxDigital\Ticketing\Enums\EventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $image
 * @property array<int, string>|null $gallery
 * @property string|null $venue_name
 * @property string|null $venue_address
 * @property float|null $venue_lat
 * @property float|null $venue_lng
 * @property EventStatus $status
 * @property string $timezone
 * @property bool $is_recurring
 * @property string|null $recurrence_rule
 * @property int|null $max_capacity
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventOccurrence> $occurrences
 * @property-read Collection<int, EventCategory> $categories
 * @property-read Collection<int, Speaker> $speakers
 * @property-read Collection<int, Sponsor> $sponsors
 * @property-read Collection<int, TicketType> $ticketTypes
 * @property-read Collection<int, RegistrationQuestion> $registrationQuestions
 * @property-read Collection<int, Order> $orders
 */
class Event extends Model
{
    use HasFactory;

    protected $table = 'ticketing_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'gallery' => 'array',
            'is_recurring' => 'boolean',
            'venue_lat' => 'float',
            'venue_lng' => 'float',
            'max_capacity' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ticketing_model('event_occurrence'), 'event_id')->orderBy('starts_at');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            ticketing_model('event_category'),
            'ticketing_event_category',
            'event_id',
            'event_category_id',
        );
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(ticketing_model('speaker'), 'ticketing_event_speaker', 'event_id', 'speaker_id')
            ->withPivot(['role', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function sponsors(): BelongsToMany
    {
        return $this->belongsToMany(ticketing_model('sponsor'), 'ticketing_event_sponsor', 'event_id', 'sponsor_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(ticketing_model('ticket_type'), 'event_id')->orderBy('sort_order');
    }

    public function registrationQuestions(): HasMany
    {
        return $this->hasMany(ticketing_model('registration_question'), 'event_id')->orderBy('sort_order');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ticketing_model('order'), 'event_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === EventStatus::Published;
    }

    /**
     * Event-specific questions plus global (event_id = null) questions.
     *
     * @return Builder<RegistrationQuestion>
     */
    public function allRegistrationQuestions(): Builder
    {
        /** @var Builder<RegistrationQuestion> $query */
        $query = ticketing_model('registration_question')::query()
            ->where(fn (Builder $q) => $q->where('event_id', $this->getKey())->orWhereNull('event_id'))
            ->orderBy('sort_order');

        return $query;
    }

    protected static function newFactory(): Factory
    {
        return EventFactory::new();
    }
}
