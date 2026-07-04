<?php

namespace AtxDigital\Ticketing\Http\Controllers;

use AtxDigital\Ticketing\Contracts\PdfGeneratorContract;
use AtxDigital\Ticketing\Contracts\QrCodeGeneratorContract;
use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Mail\OrderConfirmationMail;
use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Services\IcsGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Browser previews of outbound artefacts (confirmation email, ticket PDF,
 * calendar invite) for development/styling. Only mounted when previews are
 * enabled, behind auth middleware — see config('ticketing.previews').
 */
class DevPreviewController extends Controller
{
    public function index(): View
    {
        return view('ticketing::dev.index', [
            'order' => $this->latestOrder(),
            'attendee' => $this->latestAttendee(),
        ]);
    }

    public function mail(): mixed
    {
        $order = $this->latestOrder() ?? abort(404, 'No orders yet — seed or create one first (see the demo seeder).');

        return (new OrderConfirmationMail($order))->render();
    }

    public function ticket(Request $request, QrCodeGeneratorContract $qr, PdfGeneratorContract $pdf): Response
    {
        $attendee = $this->latestAttendee() ?? abort(404, 'No attendees yet — seed or create an order first.');

        $order = $attendee->orderItem?->order;

        $data = [
            'attendee' => $attendee,
            'orderItem' => $attendee->orderItem,
            'ticketType' => $attendee->orderItem?->ticketType,
            'order' => $order,
            'event' => $order?->event,
            'occurrence' => $order?->occurrence,
            'qrDataUri' => 'data:image/png;base64,'.base64_encode($qr->generate($attendee->checkin_token)),
        ];

        // ?html=1 renders the raw Blade view for quicker CSS iteration.
        if ($request->boolean('html')) {
            return response(view('ticketing::pdf.ticket', $data)->render());
        }

        return response($pdf->generate('ticketing::pdf.ticket', $data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="ticket-preview.pdf"',
        ]);
    }

    public function ics(IcsGenerator $generator): Response
    {
        $order = $this->latestOrder() ?? abort(404, 'No orders yet — seed or create one first.');
        $occurrence = $order->occurrence ?? abort(404, 'The latest order has no occurrence.');

        return response($generator->forOccurrence($occurrence), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    protected function latestOrder(): ?Order
    {
        /** @var Order|null */
        return ticketing_model('order')::query()
            ->with(['items.attendees', 'items.ticketType', 'event', 'occurrence'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [OrderStatus::Paid->value])
            ->latest()
            ->first();
    }

    protected function latestAttendee(): ?Attendee
    {
        /** @var Attendee|null */
        return ticketing_model('attendee')::query()
            ->with(['orderItem.ticketType', 'orderItem.order.event', 'orderItem.order.occurrence'])
            ->latest()
            ->first();
    }
}
