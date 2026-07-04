<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $attendee_id
 * @property Carbon $checked_in_at
 * @property int|null $checked_in_by
 * @property array<string, mixed>|null $metadata
 * @property-read Attendee|null $attendee
 */
class CheckIn extends Model
{
    use HasFactory;

    protected $table = 'ticketing_check_ins';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('attendee'), 'attendee_id');
    }

    public function checkedInBy(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'checked_in_by');
    }

    protected static function newFactory(): Factory
    {
        return CheckInFactory::new();
    }
}
