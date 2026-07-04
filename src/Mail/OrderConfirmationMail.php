<?php

namespace AtxDigital\Ticketing\Mail;

use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Services\IcsGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['items.attendees', 'items.ticketType', 'event', 'occurrence']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Your tickets for %s (%s)', $this->order->event?->title, $this->order->order_number),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'ticketing::mail.order-confirmation',
            with: ['order' => $this->order],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $disk = (string) config('ticketing.storage.disk', 'local');
        $attachments = [];

        foreach ($this->order->attendees as $index => $attendee) {
            if ($attendee->ticket_pdf_path === null) {
                continue;
            }

            $attachments[] = Attachment::fromStorageDisk($disk, $attendee->ticket_pdf_path)
                ->as(sprintf('ticket-%d-%s.pdf', $index + 1, str($attendee->name)->slug()))
                ->withMime('application/pdf');
        }

        if (config('ticketing.features.calendar_invites', true) && $this->order->occurrence !== null) {
            $ics = app(IcsGenerator::class)->forOccurrence($this->order->occurrence);

            $attachments[] = Attachment::fromData(fn () => $ics, 'event.ics')
                ->withMime('text/calendar');
        }

        return $attachments;
    }
}
