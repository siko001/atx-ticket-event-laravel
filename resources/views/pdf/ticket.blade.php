<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; margin: 0; padding: 0; font-size: 13px; }
        .ticket { border: 2px solid #1a1a1a; border-radius: 8px; margin: 24px; padding: 0; }
        .header { background: #1a1a1a; color: #ffffff; padding: 20px 28px; border-radius: 5px 5px 0 0; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0 0; font-size: 13px; color: #cccccc; }
        .body { padding: 24px 28px; }
        .details { width: 100%; border-collapse: collapse; }
        .details td { vertical-align: top; padding: 0; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #888888; margin-bottom: 2px; }
        .value { font-size: 14px; margin-bottom: 14px; }
        .qr { text-align: center; width: 200px; }
        .qr img { width: 170px; height: 170px; }
        .token { font-size: 9px; color: #999999; word-break: break-all; }
        .footer { border-top: 1px dashed #bbbbbb; padding: 14px 28px; font-size: 11px; color: #777777; }
        .sponsors { margin-top: 8px; }
        .sponsors img { height: 28px; margin-right: 14px; }
    </style>
</head>
<body>
<div class="ticket">
    <div class="header">
        <h1>{{ $event?->title }}</h1>
        <p>{{ $ticketType?->name }}</p>
    </div>
    <div class="body">
        <table class="details">
            <tr>
                <td>
                    <div class="label">Attendee</div>
                    <div class="value">{{ $attendee->name }}</div>

                    @if ($occurrence)
                        <div class="label">Date &amp; time</div>
                        <div class="value">
                            {{ $occurrence->starts_at->timezone($event?->timezone ?? 'UTC')->format('l j F Y, H:i') }}
                            @if ($occurrence->ends_at)
                                – {{ $occurrence->ends_at->timezone($event?->timezone ?? 'UTC')->format('H:i') }}
                            @endif
                            ({{ $event?->timezone ?? 'UTC' }})
                        </div>
                    @endif

                    @if ($event?->venue_name)
                        <div class="label">Venue</div>
                        <div class="value">
                            {{ $event->venue_name }}
                            @if ($event->venue_address)<br>{{ $event->venue_address }}@endif
                        </div>
                    @endif

                    <div class="label">Order</div>
                    <div class="value">{{ $order->order_number }}</div>
                </td>
                <td class="qr">
                    <img src="{{ $qrDataUri }}" alt="Check-in QR code">
                    <div class="token">{{ $attendee->checkin_token }}</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="footer">
        Present this ticket (printed or on screen) at the entrance. Each QR code admits one person.
        @if ($event && $event->sponsors->isNotEmpty())
            <div class="sponsors">
                @foreach ($event->sponsors as $sponsor)
                    @if ($sponsor->logo)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('ticketing.storage.disk', 'local'))->path($sponsor->logo) }}" alt="{{ $sponsor->name }}">
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
</body>
</html>
