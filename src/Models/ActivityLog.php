<?php

namespace AtxDigital\Ticketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sync/order activity log entries shown under System → Logs.
 *
 * @property int $id
 * @property string $channel
 * @property string $level
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ticketing_logs';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
