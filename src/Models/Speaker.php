<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\SpeakerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $name
 * @property string|null $bio
 * @property string|null $photo
 * @property string|null $organisation
 * @property array<string, string>|null $social_links
 * @property-read Pivot|null $pivot
 * @property-read Collection<int, Event> $events
 */
class Speaker extends Model
{
    use HasFactory;

    protected $table = 'ticketing_speakers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(ticketing_model('event'), 'ticketing_event_speaker', 'speaker_id', 'event_id')
            ->withPivot(['role', 'sort_order']);
    }

    protected static function newFactory(): Factory
    {
        return SpeakerFactory::new();
    }
}
