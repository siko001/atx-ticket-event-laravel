<?php

namespace AtxDigital\Ticketing\Jobs;

use AtxDigital\Ticketing\Mail\OrderConfirmationMail;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        Mail::to($this->order->purchaser_email)->send(new OrderConfirmationMail($this->order));
    }
}
