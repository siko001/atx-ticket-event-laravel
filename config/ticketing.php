<?php

use AtxDigital\Ticketing\Models;
use AtxDigital\Ticketing\Pricing\Rules\EarlyBirdRule;
use AtxDigital\Ticketing\Pricing\Rules\PromoCodeRule;
use AtxDigital\Ticketing\Pricing\Rules\QuantityBreakRule;
use AtxDigital\Ticketing\Registration\QuestionTypes\CheckboxQuestion;
use AtxDigital\Ticketing\Registration\QuestionTypes\RadioQuestion;
use AtxDigital\Ticketing\Registration\QuestionTypes\SelectQuestion;
use AtxDigital\Ticketing\Registration\QuestionTypes\TextareaQuestion;
use AtxDigital\Ticketing\Registration\QuestionTypes\TextQuestion;

return [

    /*
    |--------------------------------------------------------------------------
    | Currency & money
    |--------------------------------------------------------------------------
    | All monetary values are stored as integers in minor units (cents).
    | The currency is the default for new ticket types and orders.
    */

    'currency' => env('TICKETING_CURRENCY', 'eur'),

    'order_number_prefix' => env('TICKETING_ORDER_PREFIX', 'TKT'),

    /*
    |--------------------------------------------------------------------------
    | VAT
    |--------------------------------------------------------------------------
    | mode:
    |  - "none":       no VAT is added.
    |  - "flat":       a flat percentage (rate) is added at order-total level.
    |  - "stripe_tax": Stripe Tax computes tax inside Checkout; local vat_total
    |                  stays 0 at order creation.
    */

    'vat' => [
        'mode' => env('TICKETING_VAT_MODE', 'none'),
        'rate' => (float) env('TICKETING_VAT_RATE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'public' => env('STRIPE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    | success_url / cancel_url are the defaults used when the caller (e.g. the
    | WordPress plugin) does not send its own return URLs. When
    | allowed_return_hosts is non-empty, caller-provided URLs must match one of
    | the listed hosts or they are rejected.
    */

    'checkout' => [
        'success_url' => env('TICKETING_CHECKOUT_SUCCESS_URL'),
        'cancel_url' => env('TICKETING_CHECKOUT_CANCEL_URL'),
        'allowed_return_hosts' => [],
        'rate_limit_per_minute' => 20,
        'max_quantity_per_type' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Check-in
    |--------------------------------------------------------------------------
    | token_ttl_days: null means check-in tokens never expire. When set, a
    | token stops being scannable N days after the order was paid.
    | The check-in routes are guarded by the "ticketing.checkin" gate; define
    | your own gate with that name in the host app to scope access.
    */

    'checkin' => [
        'token_length' => 32,
        'token_ttl_days' => null,
        'middleware' => ['web', 'auth'],
    ],

    'qr' => [
        'size' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring events
    |--------------------------------------------------------------------------
    | Occurrences are materialized from the RRULE on a rolling window by the
    | ticketing:materialize-occurrences command. When schedule = true the
    | package registers a daily scheduled run automatically.
    */

    'recurrence' => [
        'window_months' => 12,
        'schedule' => true,
    ],

    'storage' => [
        'disk' => env('TICKETING_STORAGE_DISK', 'local'),
        'ticket_path' => 'ticketing/tickets',
        // Public disk used for uploaded media (speaker photos, sponsor logos)
        // when building public URLs for the WordPress payload.
        'media_disk' => env('TICKETING_MEDIA_DISK', 'public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature toggles
    |--------------------------------------------------------------------------
    */

    'features' => [
        'calendar_invites' => true,
        'quantity_break_rules' => true,
        'check_in' => true,

        // Show the ticketing metrics (events, revenue, tickets, check-ins)
        // on the Filament dashboard. Set to false to hide them.
        'dashboard_metrics' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'api_prefix' => 'api/ticketing',
        'api_middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dev previews
    |--------------------------------------------------------------------------
    | Browser previews of the outbound artefacts (confirmation email, ticket
    | PDF, calendar invite) at /ticketing/dev — useful while styling the
    | publishable views. Requires a logged-in (Filament) user. enabled = null
    | means "local environment only".
    */

    'previews' => [
        'enabled' => env('TICKETING_PREVIEWS_ENABLED'),
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | WordPress sync
    |--------------------------------------------------------------------------
    | When wp_webhook_url is set, publishing/updating/cancelling/deleting a
    | published event pushes a signed JSON payload to that URL. See
    | ARCHITECTURE.md for the payload & signature contract.
    */

    'wp_webhook_url' => env('TICKETING_WP_WEBHOOK_URL'),
    'wp_webhook_secret' => env('TICKETING_WP_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Swappable models
    |--------------------------------------------------------------------------
    | Extend any model in your app (class ClientEvent extends Event) and point
    | the key at your subclass. The package always resolves models through
    | ticketing_model('key') — never the concrete class.
    */

    'models' => [
        'event' => Models\Event::class,
        'event_occurrence' => Models\EventOccurrence::class,
        'event_category' => Models\EventCategory::class,
        'speaker' => Models\Speaker::class,
        'sponsor' => Models\Sponsor::class,
        'ticket_type' => Models\TicketType::class,
        'pricing_rule' => Models\PricingRule::class,
        'discount_code' => Models\DiscountCode::class,
        'registration_question' => Models\RegistrationQuestion::class,
        'order' => Models\Order::class,
        'order_item' => Models\OrderItem::class,
        'attendee' => Models\Attendee::class,
        'registration_response' => Models\RegistrationResponse::class,
        'check_in' => Models\CheckIn::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing rules
    |--------------------------------------------------------------------------
    | Maps a PricingRule "type" discriminator to the class evaluating it.
    | Register your own rule (e.g. member pricing) by adding an entry here;
    | the class must implement PricingRuleContract.
    */

    'pricing_rules' => [
        'early_bird' => EarlyBirdRule::class,
        'promo_code' => PromoCodeRule::class,
        'quantity_break' => QuantityBreakRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration question types
    |--------------------------------------------------------------------------
    | Maps a RegistrationQuestion "type" string to the class that renders and
    | validates it. Add a new type without any migration by registering a
    | class implementing QuestionTypeContract.
    */

    'question_types' => [
        'text' => TextQuestion::class,
        'textarea' => TextareaQuestion::class,
        'select' => SelectQuestion::class,
        'checkbox' => CheckboxQuestion::class,
        'radio' => RadioQuestion::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sponsor tiers
    |--------------------------------------------------------------------------
    | When non-empty, the Sponsor form shows a select of these tiers instead
    | of a free-text input.
    */

    'sponsor_tiers' => [],

    /*
    |--------------------------------------------------------------------------
    | Ticket type presets
    |--------------------------------------------------------------------------
    | Optional name suggestions offered when creating ticket types
    | (e.g. ['General admission', 'VIP', 'Student']).
    */

    'ticket_type_presets' => [],
];
