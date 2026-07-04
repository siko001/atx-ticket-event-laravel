<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\EventCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $colour
 * @property int|null $parent_id
 * @property-read EventCategory|null $parent
 * @property-read Collection<int, EventCategory> $children
 * @property-read Collection<int, Event> $events
 */
class EventCategory extends Model
{
    use HasFactory;

    protected $table = 'ticketing_event_categories';

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('event_category'), 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ticketing_model('event_category'), 'parent_id');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            ticketing_model('event'),
            'ticketing_event_category',
            'event_category_id',
            'event_id',
        );
    }

    protected static function newFactory(): Factory
    {
        return EventCategoryFactory::new();
    }
}
