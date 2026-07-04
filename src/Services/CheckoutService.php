<?php

namespace AtxDigital\Ticketing\Services;

use AtxDigital\Ticketing\Contracts\PaymentGatewayContract;
use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Events\OrderCreated;
use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Models\DiscountCode;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Models\EventOccurrence;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Models\OrderItem;
use AtxDigital\Ticketing\Models\PricingRule;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use AtxDigital\Ticketing\Models\TicketType;
use AtxDigital\Ticketing\Pricing\DiscountCodeData;
use AtxDigital\Ticketing\Pricing\PricingContext;
use AtxDigital\Ticketing\Pricing\PricingEngine;
use AtxDigital\Ticketing\Pricing\PricingRuleDefinition;
use AtxDigital\Ticketing\Registration\RegistrationFormBuilder;
use AtxDigital\Ticketing\Support\Url;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected PricingEngine $engine,
        protected PaymentGatewayContract $gateway,
        protected RegistrationFormBuilder $formBuilder,
        protected OrderPaymentService $payments,
    ) {}

    /**
     * Create a pending order (with items, attendees and responses) and return
     * the hosted checkout URL. Free orders are marked paid immediately.
     *
     * @param  array<string, mixed>  $data  Validated checkout payload (see CheckoutRequest).
     *
     * @throws ValidationException
     */
    public function checkout(Event $event, array $data, ?Connection $connection = null): CheckoutResult
    {
        if (! $event->isPublished()) {
            throw ValidationException::withMessages(['event' => 'This event is not open for registration.']);
        }

        $occurrence = $this->resolveOccurrence($event, (int) $data['occurrence_id']);
        $items = $this->resolveItems($event, $data['items']);
        $discountCode = $this->resolveDiscountCode($data['discount_code'] ?? null, $items);
        $this->assertCapacity($occurrence, $items);
        $this->assertReturnUrlsAllowed($data);

        $questions = $event->allRegistrationQuestions()->get();
        $attendeeSets = $this->buildAttendeeSets($event, $items, $data, $questions);

        $now = new DateTimeImmutable;
        $discountData = $discountCode === null ? null : DiscountCodeData::fromModel($discountCode);

        $subtotal = 0;
        $discountTotal = 0;
        $pricedItems = [];

        foreach ($items as [$ticketType, $quantity]) {
            $context = new PricingContext(
                basePrice: $ticketType->base_price,
                currency: $ticketType->currency,
                quantity: $quantity,
                purchasedAt: $now,
                ticketTypeId: (int) $ticketType->getKey(),
                attendeeAttributes: (array) ($data['purchaser'] ?? []),
                discountCode: $discountData,
            );

            $result = $this->engine->calculate($context, $this->ruleDefinitionsFor($ticketType, $discountData));

            $subtotal += $result->unitPriceBeforeDiscount() * $quantity;
            $discountTotal += $result->unitDiscount * $quantity;

            $pricedItems[] = [$ticketType, $quantity, $result];
        }

        $net = $subtotal - $discountTotal;
        $vatTotal = config('ticketing.vat.mode') === 'flat'
            ? (int) round($net * ((float) config('ticketing.vat.rate', 0)) / 100)
            : 0;

        $order = DB::transaction(function () use ($event, $occurrence, $data, $discountCode, $pricedItems, $attendeeSets, $subtotal, $discountTotal, $vatTotal, $net, $connection) {
            /** @var Order $order */
            $order = ticketing_model('order')::query()->create([
                'event_id' => $event->getKey(),
                'event_occurrence_id' => $occurrence->getKey(),
                'discount_code_id' => $discountCode?->getKey(),
                'connection_id' => $connection?->exists ? $connection->getKey() : null,
                'is_test' => (bool) ($connection->is_test_mode ?? false),
                'currency' => $pricedItems[0][0]->currency,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'vat_total' => $vatTotal,
                'total' => $net + $vatTotal,
                'purchaser_name' => $data['purchaser']['name'],
                'purchaser_email' => $data['purchaser']['email'],
                'purchaser_phone' => $data['purchaser']['phone'] ?? null,
                'purchaser_organisation' => $data['purchaser']['organisation'] ?? null,
                'purchaser_country' => $data['purchaser']['country'] ?? null,
                'success_url' => $data['success_url'] ?? null,
                'cancel_url' => $data['cancel_url'] ?? null,
            ]);

            foreach ($pricedItems as [$ticketType, $quantity, $result]) {
                /** @var OrderItem $item */
                $item = $order->items()->create([
                    'ticket_type_id' => $ticketType->getKey(),
                    'quantity' => $quantity,
                    'unit_price' => $result->unitPrice,
                    'pricing_snapshot' => [
                        'base_price' => $ticketType->base_price,
                        'applied_rules' => $result->applied,
                    ],
                ]);

                foreach ($attendeeSets[$ticketType->getKey()] as $attendeeData) {
                    /** @var Attendee $attendee */
                    $attendee = $item->attendees()->create([
                        'name' => $attendeeData['name'],
                        'email' => $attendeeData['email'] ?? null,
                        'phone' => $attendeeData['phone'] ?? null,
                        'organisation' => $attendeeData['organisation'] ?? null,
                        'country' => $attendeeData['country'] ?? null,
                    ]);

                    foreach ($attendeeData['responses'] as $response) {
                        $attendee->responses()->create($response);
                    }
                }
            }

            return $order;
        });

        event(new OrderCreated($order));

        if ($order->total === 0) {
            $this->payments->markPaid($order);

            $successUrl = (string) ($order->success_url ?: config('ticketing.checkout.success_url', '/'));

            return new CheckoutResult($order, Url::appendQuery($successUrl, ['order' => $order->order_number]));
        }

        return new CheckoutResult($order, $this->gateway->createCheckoutSession($order));
    }

    protected function resolveOccurrence(Event $event, int $occurrenceId): EventOccurrence
    {
        /** @var EventOccurrence|null $occurrence */
        $occurrence = $event->occurrences()->whereKey($occurrenceId)->first();

        if ($occurrence === null) {
            throw ValidationException::withMessages(['occurrence_id' => 'Unknown occurrence for this event.']);
        }

        if ($occurrence->status === OccurrenceStatus::Cancelled) {
            throw ValidationException::withMessages(['occurrence_id' => 'This date has been cancelled.']);
        }

        if (($occurrence->ends_at ?? $occurrence->starts_at)->isPast()) {
            throw ValidationException::withMessages(['occurrence_id' => 'This date has already taken place.']);
        }

        return $occurrence;
    }

    /**
     * @param  array<int, array{ticket_type_id: int|string, quantity: int|string}>  $rawItems
     * @return list<array{0: TicketType, 1: int}>
     */
    protected function resolveItems(Event $event, array $rawItems): array
    {
        $items = [];

        foreach ($rawItems as $index => $rawItem) {
            /** @var TicketType|null $ticketType */
            $ticketType = $event->ticketTypes()->whereKey((int) $rawItem['ticket_type_id'])->first();

            if ($ticketType === null || ! $ticketType->is_active) {
                throw ValidationException::withMessages([
                    "items.{$index}.ticket_type_id" => 'This ticket type is not available.',
                ]);
            }

            $quantity = (int) $rawItem['quantity'];
            $remaining = $ticketType->remainingQuantity();

            if ($remaining !== null && $quantity > $remaining) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => $remaining === 0
                        ? "{$ticketType->name} is sold out."
                        : "Only {$remaining} of {$ticketType->name} left.",
                ]);
            }

            $items[] = [$ticketType, $quantity];
        }

        return $items;
    }

    /**
     * @param  list<array{0: TicketType, 1: int}>  $items
     */
    protected function resolveDiscountCode(?string $code, array $items): ?DiscountCode
    {
        if (blank($code)) {
            return null;
        }

        /** @var DiscountCode|null $discountCode */
        $discountCode = ticketing_model('discount_code')::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim((string) $code))])
            ->first();

        if ($discountCode === null) {
            throw ValidationException::withMessages(['discount_code' => 'This code is not valid.']);
        }

        if (! $discountCode->hasUsesLeft()) {
            throw ValidationException::withMessages(['discount_code' => 'This code has been fully redeemed.']);
        }

        if (! $discountCode->isValidAt(now())) {
            throw ValidationException::withMessages(['discount_code' => 'This code is not currently valid.']);
        }

        $appliesToAny = collect($items)->contains(
            fn (array $item) => $discountCode->appliesToTicketType((int) $item[0]->getKey())
        );

        if (! $appliesToAny) {
            throw ValidationException::withMessages(['discount_code' => 'This code does not apply to the selected tickets.']);
        }

        return $discountCode;
    }

    /**
     * @param  list<array{0: TicketType, 1: int}>  $items
     */
    protected function assertCapacity(EventOccurrence $occurrence, array $items): void
    {
        $remaining = $occurrence->remainingCapacity();

        if ($remaining === null) {
            return;
        }

        $requested = collect($items)->sum(fn (array $item) => $item[1]);

        if ($requested > $remaining) {
            throw ValidationException::withMessages([
                'occurrence_id' => $remaining === 0
                    ? 'This date is fully booked.'
                    : "Only {$remaining} places left for this date.",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertReturnUrlsAllowed(array $data): void
    {
        $allowedHosts = (array) config('ticketing.checkout.allowed_return_hosts', []);

        if ($allowedHosts === []) {
            return;
        }

        foreach (['success_url', 'cancel_url'] as $key) {
            $url = $data[$key] ?? null;

            if (blank($url)) {
                continue;
            }

            $host = parse_url((string) $url, PHP_URL_HOST);

            if (! in_array($host, $allowedHosts, true)) {
                throw ValidationException::withMessages([$key => 'Return URL host is not allowed.']);
            }
        }
    }

    /**
     * Build per-unit attendee payloads (falling back to the purchaser) with
     * validated, casted registration responses. When the event requires
     * attendee details, every ticket must come with a named person instead.
     *
     * @param  list<array{0: TicketType, 1: int}>  $items
     * @param  array<string, mixed>  $data
     * @param  Collection<int, RegistrationQuestion>  $questions
     * @return array<int|string, list<array<string, mixed>>>
     */
    protected function buildAttendeeSets(Event $event, array $items, array $data, Collection $questions): array
    {
        $provided = collect((array) ($data['attendees'] ?? []))->groupBy(fn (array $a) => (int) $a['ticket_type_id']);
        $sharedAnswers = (array) ($data['answers'] ?? []);
        $sets = [];

        foreach ($items as $index => [$ticketType, $quantity]) {
            $forType = $provided->get((int) $ticketType->getKey(), collect());

            if ($forType->isNotEmpty() && $forType->count() !== $quantity) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "Attendee details for {$ticketType->name} do not match the quantity.",
                ]);
            }

            if ($event->requires_attendee_details) {
                if ($forType->count() !== $quantity) {
                    throw ValidationException::withMessages([
                        "attendees.{$ticketType->getKey()}" => "This event requires a name for every ticket: please provide {$quantity} attendee(s) for {$ticketType->name}.",
                    ]);
                }

                foreach ($forType as $unit => $attendee) {
                    if (blank($attendee['name'] ?? null)) {
                        throw ValidationException::withMessages([
                            "attendees.{$ticketType->getKey()}.{$unit}.name" => 'Each ticket needs the attendee\'s name ('.$ticketType->name.', ticket '.($unit + 1).').',
                        ]);
                    }
                }
            }

            $units = [];

            for ($unit = 0; $unit < $quantity; $unit++) {
                $attendee = $forType->get($unit) ?? [
                    'name' => $data['purchaser']['name'],
                    'email' => $data['purchaser']['email'],
                    'phone' => $data['purchaser']['phone'] ?? null,
                    'organisation' => $data['purchaser']['organisation'] ?? null,
                    'country' => $data['purchaser']['country'] ?? null,
                ];

                // Attendee email is optional when naming tickets — the
                // purchaser stays the contact (column is non-nullable).
                if (blank($attendee['email'] ?? null)) {
                    $attendee['email'] = $data['purchaser']['email'];
                }

                // Per-attendee answers win over shared top-level answers.
                $answers = array_replace($sharedAnswers, (array) ($attendee['answers'] ?? []));

                $attendee['responses'] = $this->validateAnswers($questions, $answers);
                unset($attendee['answers'], $attendee['ticket_type_id']);

                $units[] = $attendee;
            }

            $sets[$ticketType->getKey()] = $units;
        }

        return $sets;
    }

    /**
     * @param  Collection<int, RegistrationQuestion>  $questions
     * @param  array<int|string, mixed>  $answers
     * @return list<array<string, mixed>>
     */
    protected function validateAnswers(Collection $questions, array $answers): array
    {
        if ($questions->isEmpty()) {
            return [];
        }

        Validator::make(
            ['answers' => $answers],
            $this->formBuilder->validationRules($questions),
        )->validate();

        $responses = [];

        foreach ($questions as $question) {
            $value = $answers[$question->getKey()] ?? $answers[(string) $question->getKey()] ?? null;

            if ($value === null && ! $question->is_required) {
                continue;
            }

            $responses[] = [
                'registration_question_id' => $question->getKey(),
                'label' => $question->label,
                'value' => $this->formBuilder->castValue($question, $value),
            ];
        }

        return $responses;
    }

    /**
     * Active rules for the ticket type (plus global rules). When a discount
     * code is in play and no promo_code rule row exists, a synthetic one is
     * appended so codes work without per-ticket-type configuration.
     *
     * @return list<PricingRuleDefinition>
     */
    protected function ruleDefinitionsFor(TicketType $ticketType, ?DiscountCodeData $discountCode): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, PricingRule> $rules */
        $rules = ticketing_model('pricing_rule')::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('ticket_type_id', $ticketType->getKey())->orWhereNull('ticket_type_id'))
            ->orderBy('priority')
            ->get();

        $definitions = $rules->map(fn ($rule) => PricingRuleDefinition::fromModel($rule));

        if (! config('ticketing.features.quantity_break_rules', true)) {
            $definitions = $definitions->reject(fn (PricingRuleDefinition $d) => $d->type === 'quantity_break');
        }

        if ($discountCode !== null && ! $definitions->contains(fn (PricingRuleDefinition $d) => $d->type === 'promo_code')) {
            $definitions->push(new PricingRuleDefinition(type: 'promo_code', priority: PHP_INT_MAX));
        }

        return $definitions->values()->all();
    }
}
