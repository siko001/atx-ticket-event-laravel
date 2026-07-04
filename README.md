# ATX Digital — Ticketing for Laravel + Filament

A reusable, client-agnostic events & ticketing package: Filament 4 admin, Stripe Checkout payments, recurring events (RRULE), QR ticket check-in, reporting, dashboard metrics, activity logs, and a signed two-way sync with one or more WordPress sites running the companion plugin (`atx-digital-ticketing-connect`).

**Client-specific behaviour belongs in config, events and container bindings — never in edits to this package.**

- Laravel 11–13, PHP 8.3+, Filament ^4.0
- All money is stored as **integers in minor units** (cents); admin forms accept major units (5.50 → 550)
- Events carry media (image/video + gallery), optional **named tickets** (`requires_attendee_details` — a name per ticket at checkout), categories, speakers, sponsors and dynamic registration questions
- **Connections** screen manages multiple WordPress sites (own secret each, Active + Test-mode toggles, both synced two-way), each optionally with **its own Stripe test & live keys** (falling back to `STRIPE_SECRET`/`STRIPE_TEST_SECRET` in `.env`; test orders are badged and excluded from revenue), with sync/order **Logs** under System
- Quality gates: Pest (80 tests), PHPStan level 6 (larastan), Pint

---

## Installation

```bash
composer require atx-digital/ticketing
```

Installing straight from GitHub (no Packagist)? Add the VCS repo to the app's
`composer.json` first:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/siko001/atx-ticket-event-laravel" }
]
```

```bash
composer require atx-digital/ticketing:^1.0

php artisan vendor:publish --tag=ticketing-config
php artisan vendor:publish --tag=ticketing-migrations
php artisan migrate
php artisan storage:link   # for speaker photos / sponsor logos
```

Register the Filament plugin in your panel provider:

```php
use AtxDigital\Ticketing\TicketingPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(TicketingPlugin::make());
}
```

### Plugin options

```php
TicketingPlugin::make()
    ->navigationGroup('Events')       // default: "Ticketing"
    ->checkInEnabled(false)           // hide the QR scanner page
    ->reportsEnabled(false)           // hide the reports page
    ->resources([                     // replace the resource list entirely,
        App\Filament\Resources\EventResource::class,   // e.g. with subclasses
        // ...
    ]);
```

### Environment variables

```dotenv
TICKETING_CURRENCY=eur
TICKETING_ORDER_PREFIX=TKT
TICKETING_VAT_MODE=none            # none | flat | stripe_tax
TICKETING_VAT_RATE=0               # percent, used by "flat" mode

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

TICKETING_CHECKOUT_SUCCESS_URL=https://your-site.example/thanks
TICKETING_CHECKOUT_CANCEL_URL=https://your-site.example/cancelled

TICKETING_WP_WEBHOOK_URL=          # WordPress sync (optional)
TICKETING_WP_WEBHOOK_SECRET=
```

Point a Stripe webhook (test mode: `stripe listen --forward-to your-app.test/api/ticketing/stripe/webhook`) at `POST /api/ticketing/stripe/webhook`, subscribed to `checkout.session.completed`, `checkout.session.async_payment_succeeded` and `charge.refunded`.

### Queues & scheduler

Ticket generation, confirmation emails and WordPress pushes run as queued jobs — run a queue worker in production. Recurring events are expanded by `ticketing:materialize-occurrences`, which self-registers as a daily scheduled command (disable via `ticketing.recurrence.schedule`).

---

## Quickstart in a fresh app

```bash
laravel new shop && cd shop
composer require filament/filament atx-digital/ticketing
php artisan filament:install --panels
php artisan vendor:publish --tag=ticketing-config
php artisan vendor:publish --tag=ticketing-migrations
php artisan migrate
php artisan make:filament-user
```

Add `->plugin(TicketingPlugin::make())` to `app/Providers/Filament/AdminPanelProvider.php`, log in at `/admin`, create an Event (the create form also captures the first occurrence), add a Ticket Type, publish — done. Buyers check out through `POST /api/ticketing/events/{id}/checkout` (usually via the WordPress plugin).

---

## Domain model

`Event` ⇢ has many `EventOccurrence` (every registration attaches to an occurrence — one-off events simply have exactly one), `TicketType` (⇢ `PricingRule`), `RegistrationQuestion`; belongs to many `EventCategory`, `Speaker` (pivot `role`), `Sponsor`. `Order` ⇢ `OrderItem` ⇢ `Attendee` (unique `checkin_token`) ⇢ `RegistrationResponse`, `CheckIn`. Plus `DiscountCode`.

Recurrence: `recurrence_rule` holds an RFC 5545 RRULE; the earliest occurrence provides DTSTART/duration and `rlanvin/php-rrule` expands it on a rolling window (`ticketing.recurrence.window_months`) into concrete rows — never at read time.

## Domain events

| Event | Fired when |
|---|---|
| `EventPublished` | event transitions to published (also on create) |
| `EventUpdated` | a published event changes |
| `EventCancelled` | a published event is cancelled |
| `EventDeleted` | an event is deleted |
| `OrderCreated` | checkout created a pending order |
| `OrderPaid` | payment confirmed (webhook or free order) |
| `OrderRefunded` | refund confirmed |
| `TicketGenerated` | an attendee's PDF/QR was produced |
| `AttendeeCheckedIn` | a ticket was scanned successfully |
| `DiscountCodeRedeemed` | a paid order consumed a code |

These are the extension seam. Typical listeners: sync attendees to a CRM on `OrderPaid`; the built-in `DispatchWordPressSync` listens to the four event-lifecycle events to push to WordPress; badge printing on `AttendeeCheckedIn`.

---

## Extension points

### Swap or extend models

```php
class ClientEvent extends \AtxDigital\Ticketing\Models\Event
{
    public function programme() { return $this->hasMany(ProgrammeItem::class, 'event_id'); }
}

// config/ticketing.php
'models' => [
    'event' => App\Models\ClientEvent::class,
    // ...
],
```

The package resolves every model through `ticketing_model('event')` — internally and in relationships — so your subclass is used everywhere.

### Custom pricing rules

```php
class MemberPricingRule implements \AtxDigital\Ticketing\Contracts\PricingRuleContract
{
    public function apply(int $unitPrice, array $config, PricingContext $context): ?RuleApplication
    {
        if (! ($context->attendeeAttributes['is_member'] ?? false)) {
            return null;
        }

        return new RuleApplication((int) round($unitPrice * 0.8), 'Member pricing');
    }
}

// config/ticketing.php
'pricing_rules' => [
    // ...
    'member' => App\Pricing\MemberPricingRule::class,
],
```

Rules are pure functions over a `PricingContext` — unit-testable with no database. `PricingRule` rows reference the `type` key and carry per-rule JSON config; the engine evaluates active rules in `priority` order (lowest first). When a buyer supplies a discount code and no `promo_code` rule row exists, a synthetic one is appended automatically.

### Custom registration question types

Implement `QuestionTypeContract` (Filament field + validation rules + value casting) and register it under `config('ticketing.question_types')`. No migration required — `type` is a plain string column.

### Swap infrastructure

```php
$this->app->singleton(PdfGeneratorContract::class, BrowsershotPdfGenerator::class);
$this->app->singleton(QrCodeGeneratorContract::class, MyQrGenerator::class);
$this->app->singleton(PaymentGatewayContract::class, MyGateway::class);
```

Dompdf is the default PDF engine because it is pure PHP and runs on any host; rebind for Browsershot if your server has Chromium and you want richer CSS.

### Filament

Every resource exposes `getModel()` via `ticketing_model()`, and `EventResource::formComponents()` / report pages are designed for subclassing: extend the resource, override/append, then hand your subclass to `TicketingPlugin::make()->resources([...])`. Global component tweaks work through Filament's standard `Component::configureUsing()` hook.

### Check-in authorization

Routes are guarded by the `ticketing.checkin` gate. Define your own before the package boots to scope access:

```php
Gate::define('ticketing.checkin', fn (User $user) => $user->hasRole('door-staff'));
```

The fallback gate defers to a `canAccessTicketingCheckIn()` method on your user model when present, otherwise allows any authenticated panel user.

---

## HTTP surface

| Route | Purpose |
|---|---|
| `POST /api/ticketing/events/{id}/checkout` | public checkout (rate-limited) — creates the order and returns the Stripe Checkout URL |
| `POST /api/ticketing/stripe/webhook` | Stripe webhook (signature-verified) |
| `POST /ticketing/checkin/{token}` | scan a ticket (auth + `ticketing.checkin` gate, idempotent) |
| `GET /ticketing/checkin/occurrences/{id}/stats` | live counts for the scanner |
| `GET /ticketing/dev` | dev previews of email/ticket PDF/ICS (auth; local-only by default) |
| `GET /api/ticketing/wp/ping` | WP plugin "Test connection" (HMAC-signed) |
| `GET /api/ticketing/wp/events` | WP plugin "Sync now" — full published-events pull (HMAC-signed) |
| `POST /api/ticketing/wp/mode` | WP toggled test mode locally (HMAC-signed) |

Full request/response contracts — including the signed WordPress webhook payload — live in [ARCHITECTURE.md](ARCHITECTURE.md).

## Testing

```bash
composer test        # pint --test + phpstan + pest
composer test:unit   # pest only
```

## Notes

- Deleting an event with orders is blocked at the database level (`RESTRICT`) — cancel instead; financial records stay intact.
- `checkout.session.completed` with `payment_status !== 'paid'` (async payment methods) is ignored; fulfilment happens on `checkout.session.async_payment_succeeded`.
- Free orders (total 0) skip Stripe entirely and are fulfilled immediately.

## Releasing a new version

Composer detects updates from git tags — no registry step needed with the VCS repo.

```bash
# from this package's directory, after committing your changes:
git tag -a v1.2.1 -m "v1.2.1"        # pick the next semver: fix = patch, feature = minor, breaking = major
git push origin main --tags           # pushes the branch AND the tag

# then, in any app that uses the package:
composer outdated atx-digital/ticketing   # shows the new version
composer update atx-digital/ticketing     # installs it
```

Forgot which tags exist? `git tag` (local) / `git ls-remote --tags origin` (GitHub).
Tagged the wrong commit? `git tag -d v1.2.1 && git push origin :refs/tags/v1.2.1`, then re-tag.
