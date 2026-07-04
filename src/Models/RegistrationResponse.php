<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\RegistrationResponseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $attendee_id
 * @property int|null $registration_question_id
 * @property string $label
 * @property string|null $value
 * @property-read Attendee|null $attendee
 * @property-read RegistrationQuestion|null $question
 */
class RegistrationResponse extends Model
{
    use HasFactory;

    protected $table = 'ticketing_registration_responses';

    protected $guarded = [];

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('attendee'), 'attendee_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('registration_question'), 'registration_question_id');
    }

    protected static function newFactory(): Factory
    {
        return RegistrationResponseFactory::new();
    }
}
