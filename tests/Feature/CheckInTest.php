<?php

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Events\AttendeeCheckedIn;
use AtxDigital\Ticketing\Models\Attendee;
use AtxDigital\Ticketing\Models\CheckIn;
use AtxDigital\Ticketing\Models\Order;
use AtxDigital\Ticketing\Models\OrderItem;
use AtxDigital\Ticketing\Tests\Support\TestUser;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;

function makeAttendee(OrderStatus $orderStatus = OrderStatus::Paid): Attendee
{
    [$event, $occurrence, $ticketType] = makePurchasableEvent();

    $order = Order::factory()->create([
        'event_id' => $event->getKey(),
        'event_occurrence_id' => $occurrence->getKey(),
        'status' => $orderStatus,
        'paid_at' => $orderStatus === OrderStatus::Paid ? now() : null,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'ticket_type_id' => $ticketType->getKey(),
    ]);

    return Attendee::factory()->create(['order_item_id' => $item->getKey()]);
}

function scan(Attendee $attendee): TestResponse
{
    return test()->postJson("/ticketing/checkin/{$attendee->checkin_token}", ['source' => 'test']);
}

beforeEach(function () {
    $this->user = TestUser::create(['name' => 'Door Staff', 'email' => 'door@example.test']);
});

it('requires authentication', function () {
    $attendee = makeAttendee();

    scan($attendee)->assertUnauthorized();
});

it('denies users failing the checkin gate', function () {
    Gate::define('ticketing.checkin', fn ($user) => false);

    $attendee = makeAttendee();

    $this->actingAs($this->user);
    scan($attendee)->assertForbidden();
});

it('checks in a paid attendee and records who scanned', function () {
    EventFacade::fake([AttendeeCheckedIn::class]);

    $attendee = makeAttendee();

    $this->actingAs($this->user);

    scan($attendee)
        ->assertOk()
        ->assertJsonPath('status', 'checked_in')
        ->assertJsonPath('attendee.name', $attendee->name);

    $attendee->refresh();
    $checkIn = CheckIn::query()->where('attendee_id', $attendee->getKey())->firstOrFail();

    expect($attendee->checked_in_at)->not->toBeNull()
        ->and($checkIn->checked_in_by)->toBe($this->user->getKey())
        ->and($checkIn->metadata['source'])->toBe('test');

    EventFacade::assertDispatched(AttendeeCheckedIn::class);
});

it('is idempotent: a second scan reports the original check-in time', function () {
    $attendee = makeAttendee();

    $this->actingAs($this->user);

    scan($attendee)->assertOk()->assertJsonPath('status', 'checked_in');

    $firstCheckedInAt = $attendee->refresh()->checked_in_at;

    $this->travel(10)->minutes();

    scan($attendee)
        ->assertOk()
        ->assertJsonPath('status', 'already_checked_in')
        ->assertJsonPath('checked_in_at', $firstCheckedInAt->toIso8601String());

    expect(CheckIn::query()->where('attendee_id', $attendee->getKey())->count())->toBe(1);
});

it('rejects tickets on unpaid orders', function () {
    $attendee = makeAttendee(OrderStatus::Pending);

    $this->actingAs($this->user);

    scan($attendee)
        ->assertStatus(409)
        ->assertJsonPath('status', 'not_paid');

    expect($attendee->refresh()->checked_in_at)->toBeNull();
});

it('rejects expired tokens when a TTL is configured', function () {
    config()->set('ticketing.checkin.token_ttl_days', 1);

    $attendee = makeAttendee();
    $attendee->orderItem->order->forceFill(['paid_at' => now()->subDays(3)])->save();

    $this->actingAs($this->user);

    scan($attendee)->assertStatus(410)->assertJsonPath('status', 'expired');
});

it('returns 404 for unknown tokens', function () {
    $this->actingAs($this->user);

    $this->postJson('/ticketing/checkin/NOTAREALTOKEN123')
        ->assertNotFound()
        ->assertJsonPath('status', 'invalid');
});

it('reports live occurrence stats', function () {
    $attendee = makeAttendee();
    $occurrence = $attendee->orderItem->order->occurrence;

    // A second paid attendee on the same occurrence, not yet checked in.
    $secondItem = OrderItem::factory()->create([
        'order_id' => $attendee->orderItem->order_id,
        'ticket_type_id' => $attendee->orderItem->ticket_type_id,
    ]);
    Attendee::factory()->create(['order_item_id' => $secondItem->getKey()]);

    $this->actingAs($this->user);

    scan($attendee)->assertOk();

    $this->getJson("/ticketing/checkin/occurrences/{$occurrence->getKey()}/stats")
        ->assertOk()
        ->assertJson([
            'total' => 2,
            'checked_in' => 1,
        ]);
});
