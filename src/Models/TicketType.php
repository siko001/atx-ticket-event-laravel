<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\TicketTypeFactory;
use AtxDigital\Ticketing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string|null $description
 * @property int $base_price
 * @property string $currency
 * @property int|null $quantity_available
 * @property int $sort_order
 * @property bool $is_active
 * @property-read Event|null $event
 * @property-read Collection<int, PricingRule> $pricingRules
 * @property-read Collection<int, OrderItem> $orderItems
 */
class TicketType extends Model
{
    use HasFactory;

    protected $table = 'ticketing_ticket_types';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'quantity_available' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('event'), 'event_id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(ticketing_model('pricing_rule'), 'ticket_type_id')->orderBy('priority');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ticketing_model('order_item'), 'ticket_type_id');
    }

    /**
     * Units sold or currently reserved by a pending checkout.
     */
    public function soldCount(): int
    {
        return (int) $this->orderItems()
            ->whereHas('order', fn (Builder $q) => $q->whereIn('status', [OrderStatus::Pending, OrderStatus::Paid]))
            ->sum('quantity');
    }

    /**
     * Remaining sellable units, or null when unlimited.
     */
    public function remainingQuantity(): ?int
    {
        if ($this->quantity_available === null) {
            return null;
        }

        return max(0, $this->quantity_available - $this->soldCount());
    }

    protected static function newFactory(): Factory
    {
        return TicketTypeFactory::new();
    }
}
