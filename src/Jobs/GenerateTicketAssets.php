<?php

namespace AtxDigital\Ticketing\Jobs;

use AtxDigital\Ticketing\Contracts\PdfGeneratorContract;
use AtxDigital\Ticketing\Contracts\QrCodeGeneratorContract;
use AtxDigital\Ticketing\Events\TicketGenerated;
use AtxDigital\Ticketing\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateTicketAssets implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    public function handle(QrCodeGeneratorContract $qr, PdfGeneratorContract $pdf): void
    {
        $this->order->loadMissing(['items.attendees', 'items.ticketType', 'event', 'occurrence']);

        $disk = Storage::disk((string) config('ticketing.storage.disk', 'local'));
        $basePath = trim((string) config('ticketing.storage.ticket_path', 'ticketing/tickets'), '/');

        foreach ($this->order->items as $item) {
            foreach ($item->attendees as $attendee) {
                if ($attendee->ticket_pdf_path !== null) {
                    continue; // Idempotent on retry.
                }

                $png = $qr->generate($attendee->checkin_token);

                $content = $pdf->generate('ticketing::pdf.ticket', [
                    'attendee' => $attendee,
                    'orderItem' => $item,
                    'ticketType' => $item->ticketType,
                    'order' => $this->order,
                    'event' => $this->order->event,
                    'occurrence' => $this->order->occurrence,
                    'qrDataUri' => 'data:image/png;base64,'.base64_encode($png),
                ]);

                $path = sprintf('%s/%s-%d.pdf', $basePath, $this->order->order_number, $attendee->getKey());
                $disk->put($path, $content);

                $attendee->forceFill(['ticket_pdf_path' => $path])->save();

                event(new TicketGenerated($attendee));
            }
        }
    }
}
