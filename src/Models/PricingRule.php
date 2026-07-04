<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\PricingRuleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $ticket_type_id
 * @property string $type
 * @property array<string, mixed>|null $config
 * @property int $priority
 * @property bool $is_active
 * @property-read TicketType|null $ticketType
 */
class PricingRule extends Model
{
    use HasFactory;

    protected $table = 'ticketing_pricing_rules';

    protected $guarded = [];

    /**
     * Rule types without settings (e.g. promo_code) never populate config in
     * the form, and the column has no DB default — so default it here.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'config' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('ticket_type'), 'ticket_type_id');
    }

    protected static function newFactory(): Factory
    {
        return PricingRuleFactory::new();
    }
}
