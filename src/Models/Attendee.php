<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\AttendeeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $order_item_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $organisation
 * @property string|null $country
 * @property string $checkin_token
 * @property Carbon|null $checked_in_at
 * @property string|null $ticket_pdf_path
 * @property Carbon|null $created_at
 * @property-read OrderItem|null $orderItem
 * @property-read Order|null $order
 * @property-read Collection<int, RegistrationResponse> $responses
 * @property-read Collection<int, CheckIn> $checkIns
 */
class Attendee extends Model
{
    use HasFactory;

    protected $table = 'ticketing_attendees';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attendee) {
            if (blank($attendee->checkin_token)) {
                $attendee->checkin_token = static::generateCheckinToken();
            }
        });
    }

    public static function generateCheckinToken(): string
    {
        $length = (int) config('ticketing.checkin.token_length', 32);

        do {
            $token = Str::random($length);
        } while (static::query()->where('checkin_token', $token)->exists());

        return $token;
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('order_item'), 'order_item_id');
    }

    public function order(): HasOneThrough
    {
        return $this->hasOneThrough(
            ticketing_model('order'),
            ticketing_model('order_item'),
            'id',
            'id',
            'order_item_id',
            'order_id',
        );
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ticketing_model('registration_response'), 'attendee_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(ticketing_model('check_in'), 'attendee_id');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    protected static function newFactory(): Factory
    {
        return AttendeeFactory::new();
    }
}
