<?php

namespace AtxDigital\Ticketing\Database\Factories;

use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Models\OrderItem;
use AtxDigital\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => 1,
            'unit_price' => 5000,
        ];
    }
}
