<x-mail::message>
# Hi {{ $attendee->name }}, here's your ticket!

You're registered for **{{ $order?->event?->title }}**
@if ($order?->purchaser_name)
(booked by {{ $order->purchaser_name }}).
@endif

@if ($order?->occurrence)
**When:** {{ $order->occurrence->starts_at->timezone($order->event?->timezone ?? 'UTC')->format('l j F Y, H:i') }}
@endif
@if ($order?->event?->venue_name)
**Where:** {{ $order->event->venue_name }}@if ($order->event->venue_address), {{ $order->event->venue_address }}@endif
@endif

Your personal ticket is attached — the QR code on it is unique to you.
Show it (printed or on your phone) at the door.

See you there!
</x-mail::message>
