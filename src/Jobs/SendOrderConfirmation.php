<?php

namespace AtxDigital\Ticketing\Jobs;

use AtxDigital\Ticketing\Mail\AttendeeTicketMail;
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

        // Attendees with their own email get their personal ticket directly
        // (the buyer above still receives every ticket).
        $this->order->loadMissing('items.attendees');

        foreach ($this->order->items as $item) {
            foreach ($item->attendees as $attendee) {
                if (filled($attendee->email) && strcasecmp($attendee->email, $this->order->purchaser_email) !== 0) {
                    Mail::to($attendee->email)->send(new AttendeeTicketMail($attendee));
                }
            }
        }
    }
}
