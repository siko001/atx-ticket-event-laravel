# Architecture & Integration Contracts

This document is the **shared reference** between the Laravel package (`atx-digital/ticketing`)
and the WordPress plugin (`atx-digital-ticketing-connect`). If you change a payload on one side,
update the other side *and this file* in the same change.

**Laravel is the source of truth.** WordPress mirrors published events read-only for public
display and proxies ticket purchases back to Laravel's checkout endpoint. WordPress never
creates or edits event data.

```
┌────────────────────────┐   signed webhook (event.*)    ┌──────────────────────────┐
│  Laravel + Filament    │ ────────────────────────────► │  WordPress               │
│  events, pricing,      │                               │  atx_event CPT mirror,   │
│  orders, tickets,      │ ◄──────────────────────────── │  [atx_events] display,   │
│  check-in, reports     │   POST /checkout (proxied)    │  buy-ticket form         │
└──────────┬─────────────┘                               └──────────────────────────┘
           │  Stripe Checkout session / webhooks
           ▼
        Stripe
```

---

## 1. Outbound webhook: Laravel → WordPress

Sent on publish/update/cancel/delete of events, and by `php artisan ticketing:push-events`
(full resync, sent as `event.updated`). Delivered by a queued job with retry/backoff
(30s, 60s, 5m, 15m).

- **URL** — `config('ticketing.wp_webhook_url')`, normally
  `https://your-site.example/wp-json/atx-ticketing/v1/webhook`
- **Method/Content type** — `POST`, `application/json`

### Signature

Shared secret: `TICKETING_WP_WEBHOOK_SECRET` (Laravel) = webhook secret in the WP plugin settings.

```
X-Atx-Ticketing-Timestamp: <unix timestamp>
X-Atx-Ticketing-Signature: sha256=<hex hmac_sha256(secret, "{timestamp}.{raw request body}")>
```

Receivers MUST:
1. Reject when the timestamp is more than 300 seconds from now (replay protection).
2. Recompute the HMAC over `timestamp + "." + raw body` and compare constant-time.
3. Respond `401` to unsigned/badly-signed requests and log the failure.
4. Be idempotent — the same payload may be delivered more than once.

### Envelope

```json
{
  "type": "event.published | event.updated | event.cancelled | event.deleted",
  "sent_at": "2026-07-03T12:00:00+00:00",
  "event": { ... }
}
```

### `event` payload (`published` / `updated` / `cancelled`)

```json
{
  "id": 12,
  "title": "Laravel Live Malta 2026",
  "slug": "laravel-live-malta-2026",
  "description": "<p>HTML from the admin editor…</p>",
  "status": "published",
  "timezone": "Europe/Malta",
  "is_recurring": false,
  "max_capacity": 120,
  "published_at": "2026-07-01T09:30:00+00:00",
  "image_url": "https://laravel-app.example/storage/ticketing/events/hero.jpg",
  "gallery_urls": ["https://laravel-app.example/storage/ticketing/events/gallery/1.jpg"],
  // image_url/gallery_urls may point at images or videos (mp4/webm/mov); WP sideloads either.
  "venue": { "name": "MCC", "address": "Valletta", "lat": 35.897, "lng": 14.512 },
  "categories": [ { "name": "Conference", "slug": "conference", "colour": "#f59e0b", "parent_slug": null } ],
  "occurrences": [
    { "id": 40, "starts_at": "2026-08-14T07:00:00+00:00", "ends_at": "2026-08-14T15:00:00+00:00",
      "capacity": 120, "status": "scheduled" }
  ],
  "speakers": [
    { "name": "Maria Vella", "bio": "…", "organisation": "Acme Cloud",
      "photo_url": "https://laravel-app.example/storage/ticketing/speakers/x.jpg",
      "social_links": { "linkedin": "https://…" }, "role": "Keynote" }
  ],
  "sponsors": [ { "name": "Hostify", "url": "https://…", "tier": "gold", "logo_url": null } ],
  "ticket_types": [
    { "id": 3, "name": "Standard", "description": "…", "price": 9500, "currency": "eur" }
  ],
  "registration_questions": [
    { "id": 5, "label": "Dietary requirements", "type": "select",
      "options": ["None", "Vegetarian"], "is_required": true }
  ],
  "checkout_url": "https://laravel-app.example/api/ticketing/events/12/checkout"
}
```

Notes:
- `price` is **minor units** (cents). Display-only — the checkout endpoint is the
  authoritative price calculator (early-bird, quantity breaks, codes).
- Cancelled occurrences are omitted. `event.cancelled` carries the full payload with
  `status: "cancelled"`; keep the post but show it as cancelled.
- `event.deleted` carries only `{ "id": 12 }` — remove/trash the mirrored post.

### Pull endpoints (WP → Laravel, same signature scheme)

Two signed GET endpoints let the plugin verify and pull instead of waiting for pushes.
They are authenticated with the **same** headers/HMAC as the webhook, computed over the
request's (empty) body — i.e. the signed string is `"{timestamp}."`:

- `GET /api/ticketing/wp/ping` → `{ ok, app, time, published_events }`.
  Backs the plugin's **Test connection** button. `401` = secret mismatch,
  `503` = Laravel has no `TICKETING_WP_WEBHOOK_SECRET` configured.
- `GET /api/ticketing/wp/events` → `{ events: [ <event payload>, … ] }` — every
  published event, same payload shape as the webhook. Backs the plugin's
  **Sync now** button (full pull for first installs or after downtime).

---

## 2. Checkout endpoint: WordPress → Laravel

`POST {checkout_url}` (see payload above) — public, rate-limited (default 20/min/IP).
The WP plugin calls this **server-side** (proxied) so nothing sensitive runs in the browser.

### Request

```json
{
  "occurrence_id": 40,
  "items": [ { "ticket_type_id": 3, "quantity": 2 } ],
  "purchaser": { "name": "Ada Lovelace", "email": "ada@example.com",
                 "phone": null, "organisation": null, "country": null },
  "attendees": [
    { "ticket_type_id": 3, "name": "Ada Lovelace", "email": "ada@example.com",
      "answers": { "5": "Vegetarian" } }
  ],
  "answers": { "5": "None" },
  "discount_code": "WELCOME10",
  "success_url": "https://wp-site.example/thanks/",
  "cancel_url": "https://wp-site.example/events/laravel-live/"
}
```

- `attendees` is optional; when omitted, one attendee per ticket is cloned from `purchaser`.
  When present, the per-type count must equal that item's quantity.
- `answers` (top level) applies to all attendees; per-attendee `answers` win.
  Keys are registration question IDs from the synced payload.
- `success_url`/`cancel_url` are optional overrides of the Laravel-side defaults. If
  `ticketing.checkout.allowed_return_hosts` is configured, other hosts are rejected.
  Laravel appends `?order={order_number}` on redirect.

### Responses

**201**
```json
{ "order_id": 55, "order_number": "TKT-9F3K21AB", "checkout_url": "https://checkout.stripe.com/…" }
```
Redirect the visitor to `checkout_url`. Free orders return the success URL directly
(already paid — no Stripe step).

**422** — validation problem (sold out, capacity, invalid code, missing required answers…):
```json
{ "message": "…", "errors": { "discount_code": ["This code is not valid."] } }
```
Show `errors` next to the form fields.

**404** unknown event · **429** rate-limited · **502** payment provider down (retry later).

---

## 3. Payment flow (Stripe)

1. Checkout creates a **pending** order (+items/attendees/responses) and one Stripe Checkout
   Session; line items use final unit prices from the pricing engine (VAT appended per
   `ticketing.vat.mode`).
2. Stripe calls `POST /api/ticketing/stripe/webhook` (`Stripe\Webhook::constructEvent`
   signature check). `checkout.session.completed` / `…async_payment_succeeded` with
   `payment_status=paid` → order marked **paid** (idempotent) → `OrderPaid`.
3. Queued chain: generate QR + PDF per attendee (`TicketGenerated` each) → send
   confirmation email with PDFs and `.ics` attached.
4. `charge.refunded` → order marked **refunded** (idempotent) → `OrderRefunded`.
   Admin-initiated refunds go through `PaymentGatewayContract::refund()` from the order screen.

The webhook handler only flips order state — fulfilment always runs on the queue.

## 4. Check-in flow

QR codes encode the attendee's `checkin_token` (unique, indexed, default 32 chars).
`POST /ticketing/checkin/{token}` (auth + `ticketing.checkin` gate) responds:

| Case | HTTP | `status` |
|---|---|---|
| first valid scan | 200 | `checked_in` |
| repeat scan | 200 | `already_checked_in` (+ original `checked_in_at`) |
| order not paid | 409 | `not_paid` |
| token TTL elapsed | 410 | `expired` |
| unknown token | 404 | `invalid` |

Every successful scan records a `CheckIn` row (who/when/device metadata) and fires
`AttendeeCheckedIn`. Row locking makes concurrent duplicate scans safe.
