<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\SponsorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $name
 * @property string|null $logo
 * @property string|null $url
 * @property string|null $tier
 * @property-read Pivot|null $pivot
 * @property-read Collection<int, Event> $events
 */
class Sponsor extends Model
{
    use HasFactory;

    protected $table = 'ticketing_sponsors';

    protected $guarded = [];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(ticketing_model('event'), 'ticketing_event_sponsor', 'sponsor_id', 'event_id')
            ->withPivot('sort_order');
    }

    protected static function newFactory(): Factory
    {
        return SponsorFactory::new();
    }
}
