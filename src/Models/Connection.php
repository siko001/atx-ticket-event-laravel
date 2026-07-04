<?php

namespace AtxDigital\Ticketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A connected WordPress site. Publish webhooks push to every active
 * connection; each site signs its pull/checkout traffic with its own secret.
 *
 * @property int $id
 * @property string $name
 * @property string $webhook_url
 * @property string $webhook_secret
 * @property bool $is_active
 * @property array<string, mixed>|null $last_test
 * @property array<string, mixed>|null $last_push
 * @property array<string, mixed>|null $last_pull
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Connection extends Model
{
    protected $table = 'ticketing_connections';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_test' => 'array',
        'last_push' => 'array',
        'last_pull' => 'array',
    ];
}
