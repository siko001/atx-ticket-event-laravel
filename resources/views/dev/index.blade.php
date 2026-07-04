<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticketing dev previews</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; max-width: 640px; margin: 3rem auto; padding: 0 1rem; color: #1a1a1a; }
        h1 { font-size: 1.4rem; }
        ul { padding-left: 1.2rem; line-height: 2; }
        .muted { color: #777; font-size: .85rem; }
        code { background: #f3f3f3; padding: .1rem .35rem; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🎟 Ticketing — dev previews</h1>
    <p class="muted">
        These previews render against the most recent order/attendee in the database.
        @if (! $order)
            <strong>No orders exist yet</strong> — run the demo seeder first: <code>php artisan db:seed --class=TicketingDemoSeeder</code>
        @else
            Currently previewing order <code>{{ $order->order_number }}</code>.
        @endif
    </p>
    <ul>
        <li><a href="{{ route('ticketing.dev.mail') }}">Order confirmation email</a> (rendered Markdown mailable)</li>
        <li><a href="{{ route('ticketing.dev.ticket') }}">Ticket PDF</a> (as generated &amp; attached to the email)</li>
        <li><a href="{{ route('ticketing.dev.ticket', ['html' => 1]) }}">Ticket PDF — raw HTML</a> (for CSS iteration)</li>
        <li><a href="{{ route('ticketing.dev.ics') }}">Calendar invite (.ics)</a> (plain text)</li>
    </ul>
    <p class="muted">
        Override these templates in your app by publishing the views:
        <code>php artisan vendor:publish --tag=ticketing-views</code>
    </p>
</body>
</html>
