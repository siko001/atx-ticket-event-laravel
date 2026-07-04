<?php

namespace AtxDigital\Ticketing\Models;

use AtxDigital\Ticketing\Database\Factories\DiscountCodeFactory;
use AtxDigital\Ticketing\Enums\DiscountType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property DiscountType $type
 * @property int $value
 * @property int|null $max_uses
 * @property int $uses_count
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property array<int, int|string>|null $ticket_type_ids
 * @property-read Collection<int, Order> $orders
 */
class DiscountCode extends Model
{
    use HasFactory;

    protected $table = 'ticketing_discount_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'ticket_type_ids' => 'array',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ticketing_model('order'), 'discount_code_id');
    }

    public function hasUsesLeft(): bool
    {
        return $this->max_uses === null || $this->uses_count < $this->max_uses;
    }

    public function isValidAt(DateTimeInterface $at): bool
    {
        if ($this->valid_from !== null && $this->valid_from->gt($at)) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until->lt($at)) {
            return false;
        }

        return $this->hasUsesLeft();
    }

    public function appliesToTicketType(int $ticketTypeId): bool
    {
        return $this->ticket_type_ids === null
            || in_array($ticketTypeId, array_map(intval(...), $this->ticket_type_ids), true);
    }

    protected static function newFactory(): Factory
    {
        return DiscountCodeFactory::new();
    }
}
