<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property int $ticket_type_id
 * @property int $quantity
 * @property int $unit_price
 * @property array<string, mixed>|null $pricing_snapshot
 * @property-read Order|null $order
 * @property-read TicketType|null $ticketType
 * @property-read Collection<int, Attendee> $attendees
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'ticketing_order_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'pricing_snapshot' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('order'), 'order_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('ticket_type'), 'ticket_type_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(ticketing_model('attendee'), 'order_item_id');
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }

    protected static function newFactory(): Factory
    {
        return OrderItemFactory::new();
    }
}
