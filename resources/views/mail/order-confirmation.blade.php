<x-mail::message>
# Thanks for your order, {{ $order->purchaser_name }}!

Your registration for **{{ $order->event?->title }}** is confirmed.

@if ($order->occurrence)
**When:** {{ $order->occurrence->starts_at->timezone($order->event?->timezone ?? 'UTC')->format('l j F Y, H:i') }}
@if ($order->event?->venue_name)
**Where:** {{ $order->event->venue_name }}@if ($order->event->venue_address), {{ $order->event->venue_address }}@endif
@endif
@endif

<x-mail::table>
| Ticket | Qty | Unit price | Total |
|:-------|:---:|-----------:|------:|
@foreach ($order->items as $item)
| {{ $item->ticketType?->name }} | {{ $item->quantity }} | {{ ticketing_money($item->unit_price, $order->currency) }} | {{ ticketing_money($item->lineTotal(), $order->currency) }} |
@endforeach
</x-mail::table>

@if ($order->discount_total > 0)
Discount: −{{ ticketing_money($order->discount_total, $order->currency) }}
@endif
@if ($order->vat_total > 0)
VAT: {{ ticketing_money($order->vat_total, $order->currency) }}
@endif

**Order total: {{ ticketing_money($order->total, $order->currency) }}**
Order reference: {{ $order->order_number }}

Your tickets are attached as PDFs — each has a QR code that will be scanned at the door.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
