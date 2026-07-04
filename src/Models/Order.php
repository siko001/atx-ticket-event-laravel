<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\OrderFactory;
use AtxDigital\Ticketing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $order_number
 * @property int $event_id
 * @property int $event_occurrence_id
 * @property int|null $discount_code_id
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $discount_total
 * @property int $vat_total
 * @property int $total
 * @property string $purchaser_name
 * @property string $purchaser_email
 * @property int|null $connection_id
 * @property bool $is_test
 * @property-read Connection|null $connection
 * @property string|null $purchaser_phone
 * @property string|null $purchaser_organisation
 * @property string|null $purchaser_country
 * @property string|null $stripe_checkout_session_id
 * @property string|null $stripe_payment_intent_id
 * @property string|null $success_url
 * @property string|null $cancel_url
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property-read Event|null $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read DiscountCode|null $discountCode
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, Attendee> $attendees
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'ticketing_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'vat_total' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (blank($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = (string) config('ticketing.order_number_prefix', 'TKT');

        do {
            $number = $prefix.'-'.strtoupper(Str::random(8));
        } while (static::query()->where('order_number', $number)->exists());

        return $number;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('event'), 'event_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('event_occurrence'), 'event_occurrence_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(ticketing_model('discount_code'), 'discount_code_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ticketing_model('order_item'), 'order_id');
    }

    public function attendees(): HasManyThrough
    {
        return $this->hasManyThrough(
            ticketing_model('attendee'),
            ticketing_model('order_item'),
            'order_id',
            'order_item_id',
        );
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }
}
