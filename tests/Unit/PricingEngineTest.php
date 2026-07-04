<?php

use AtxDigital\Ticketing\Enums\DiscountType;
use AtxDigital\Ticketing\Exceptions\UnknownPricingRuleException;
use AtxDigital\Ticketing\Pricing\DiscountCodeData;
use AtxDigital\Ticketing\Pricing\PricingContext;
use AtxDigital\Ticketing\Pricing\PricingEngine;
use AtxDigital\Ticketing\Pricing\PricingRuleDefinition;
use AtxDigital\Ticketing\Pricing\Rules\EarlyBirdRule;
use AtxDigital\Ticketing\Pricing\Rules\PromoCodeRule;
use AtxDigital\Ticketing\Pricing\Rules\QuantityBreakRule;

function pricingEngine(): PricingEngine
{
    return new PricingEngine([
        'early_bird' => EarlyBirdRule::class,
        'promo_code' => PromoCodeRule::class,
        'quantity_break' => QuantityBreakRule::class,
    ]);
}

function pricingContext(int $basePrice = 10000, int $quantity = 1, ?DiscountCodeData $code = null, ?int $ticketTypeId = 1): PricingContext
{
    return new PricingContext(
        basePrice: $basePrice,
        currency: 'eur',
        quantity: $quantity,
        purchasedAt: new DateTimeImmutable('2026-06-01 12:00:00'),
        ticketTypeId: $ticketTypeId,
        discountCode: $code,
    );
}

it('applies an early bird percentage inside the window', function () {
    $result = pricingEngine()->calculate(pricingContext(), [
        new PricingRuleDefinition('early_bird', [
            'starts_at' => '2026-05-01T00:00:00Z',
            'ends_at' => '2026-06-30T23:59:59Z',
            'value_type' => 'percentage',
            'value' => 20,
        ], id: 7),
    ]);

    expect($result->unitPrice)->toBe(8000)
        ->and($result->unitDiscount)->toBe(0)
        ->and($result->applied)->toHaveCount(1)
        ->and($result->applied[0]['type'])->toBe('early_bird')
        ->and($result->applied[0]['rule_id'])->toBe(7)
        ->and($result->applied[0]['before'])->toBe(10000)
        ->and($result->applied[0]['after'])->toBe(8000);
});

it('skips an early bird rule outside the window', function () {
    $result = pricingEngine()->calculate(pricingContext(), [
        new PricingRuleDefinition('early_bird', [
            'ends_at' => '2026-05-01T00:00:00Z',
            'value_type' => 'percentage',
            'value' => 20,
        ]),
    ]);

    expect($result->unitPrice)->toBe(10000)
        ->and($result->applied)->toBeEmpty();
});

it('applies a fixed early bird discount', function () {
    $result = pricingEngine()->calculate(pricingContext(), [
        new PricingRuleDefinition('early_bird', [
            'starts_at' => '2026-05-01T00:00:00Z',
            'ends_at' => '2026-06-30T00:00:00Z',
            'value_type' => 'fixed',
            'value' => 1500,
        ]),
    ]);

    expect($result->unitPrice)->toBe(8500);
});

it('applies a quantity break only at the threshold', function () {
    $definition = new PricingRuleDefinition('quantity_break', [
        'min_quantity' => 5,
        'value_type' => 'percentage',
        'value' => 10,
    ]);

    expect(pricingEngine()->calculate(pricingContext(quantity: 5), [$definition])->unitPrice)->toBe(9000)
        ->and(pricingEngine()->calculate(pricingContext(quantity: 4), [$definition])->unitPrice)->toBe(10000);
});

it('applies a promo code and tracks the discount separately', function () {
    $code = new DiscountCodeData('SAVE10', DiscountType::Percentage, 10);

    $result = pricingEngine()->calculate(pricingContext(code: $code), [
        new PricingRuleDefinition('promo_code', priority: PHP_INT_MAX),
    ]);

    expect($result->unitPrice)->toBe(9000)
        ->and($result->unitDiscount)->toBe(1000)
        ->and($result->unitPriceBeforeDiscount())->toBe(10000);
});

it('ignores a promo code restricted to other ticket types', function () {
    $code = new DiscountCodeData('VIPONLY', DiscountType::Percentage, 50, ticketTypeIds: [99]);

    $result = pricingEngine()->calculate(pricingContext(code: $code), [
        new PricingRuleDefinition('promo_code'),
    ]);

    expect($result->unitPrice)->toBe(10000)
        ->and($result->applied)->toBeEmpty();
});

it('never prices below zero', function () {
    $code = new DiscountCodeData('BIG', DiscountType::Fixed, 99999);

    $result = pricingEngine()->calculate(pricingContext(basePrice: 500, code: $code), [
        new PricingRuleDefinition('promo_code'),
    ]);

    expect($result->unitPrice)->toBe(0)
        ->and($result->unitDiscount)->toBe(500);
});

it('evaluates rules in priority order, lowest first', function () {
    $code = new DiscountCodeData('SAVE10', DiscountType::Percentage, 10);

    $result = pricingEngine()->calculate(pricingContext(code: $code), [
        new PricingRuleDefinition('promo_code', priority: 10),
        new PricingRuleDefinition('early_bird', [
            'starts_at' => '2026-05-01T00:00:00Z',
            'ends_at' => '2026-06-30T00:00:00Z',
            'value_type' => 'percentage',
            'value' => 20,
        ], priority: 0),
    ]);

    // 10000 → early bird 20% → 8000 → promo 10% → 7200
    expect($result->unitPrice)->toBe(7200)
        ->and($result->unitDiscount)->toBe(800)
        ->and($result->applied[0]['type'])->toBe('early_bird')
        ->and($result->applied[1]['type'])->toBe('promo_code');
});

it('throws for an unregistered rule type', function () {
    pricingEngine()->calculate(pricingContext(), [
        new PricingRuleDefinition('member_pricing'),
    ]);
})->throws(UnknownPricingRuleException::class);
