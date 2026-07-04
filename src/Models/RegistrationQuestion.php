<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\RegistrationQuestionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $event_id
 * @property array<int, int>|null $ticket_type_ids
 * @property string $label
 * @property string $type
 * @property array<int, string>|null $options
 * @property bool $is_required
 * @property int $sort_order
 * @property-read Event|null $event
 * @property-read Collection<int, RegistrationResponse> $responses
 */
class RegistrationQuestion extends Model
{
    use HasFactory;

    protected $table = 'ticketing_registration_questions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'ticket_type_ids' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('event'), 'event_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ticketing_model('registration_response'), 'registration_question_id');
    }

    protected static function newFactory(): Factory
    {
        return RegistrationQuestionFactory::new();
    }

    /**
     * Whether this question applies to attendees of the given ticket type
     * (an empty scope means it applies to everyone).
     */
    public function appliesToTicketType(int $ticketTypeId): bool
    {
        $scope = array_map('intval', (array) ($this->ticket_type_ids ?? []));

        return $scope === [] || in_array($ticketTypeId, $scope, true);
    }
}
