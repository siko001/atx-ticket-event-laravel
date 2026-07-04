<?php

namespace AtxDigital\Ticketing\Mail;

use AtxDigital\Ticketing\Models\Attendee;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A single attendee's personal ticket — sent when the buyer entered an
 * email for that attendee (the buyer still receives every ticket).
 */
class AttendeeTicketMail extends Mailable
{
    public function __construct(public Attendee $attendee)
    {
        $this->attendee->loadMissing(['orderItem.order.event', 'orderItem.order.occurrence', 'orderItem.ticketType']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Your ticket for %s', $this->attendee->orderItem?->order?->event?->title),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'ticketing::mail.attendee-ticket',
            with: [
                'attendee' => $this->attendee,
                'order' => $this->attendee->orderItem?->order,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (blank($this->attendee->ticket_pdf_path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk((string) config('ticketing.storage.disk', 'local'), $this->attendee->ticket_pdf_path)
                ->as('ticket-'.$this->attendee->getKey().'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
