<?php

namespace AtxDigital\Ticketing\WordPress;

use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use AtxDigital\Ticketing\Models\Event;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the event payload pushed to the WordPress plugin. This is a display
 * contract: it carries what the public site needs (including ticket type
 * names/prices) but never internal pricing rule logic. Documented in
 * ARCHITECTURE.md — keep the two in sync.
 */
class EventPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Event $event): array
    {
        $event->loadMissing(['occurrences', 'categories', 'speakers', 'sponsors', 'ticketTypes']);

        return [
            'id' => $event->getKey(),
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'status' => $event->status->value,
            'timezone' => $event->timezone,
            'is_recurring' => $event->is_recurring,
            'max_capacity' => $event->max_capacity,
            'requires_attendee_details' => (bool) $event->requires_attendee_details,
            'published_at' => $event->published_at?->toIso8601String(),
            'image_url' => $this->mediaUrl($event->image),
            'gallery_urls' => array_values(array_filter(array_map(
                fn (string $path) => $this->mediaUrl($path),
                $event->gallery ?? [],
            ))),
            'venue' => [
                'name' => $event->venue_name,
                'address' => $event->venue_address,
                'lat' => $event->venue_lat,
                'lng' => $event->venue_lng,
            ],
            'categories' => $event->categories->map(fn ($category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'colour' => $category->colour,
                'parent_slug' => $category->parent?->slug,
            ])->values()->all(),
            'occurrences' => $event->occurrences
                ->filter(fn ($occurrence) => $occurrence->status !== OccurrenceStatus::Cancelled)
                ->map(fn ($occurrence) => [
                    'id' => $occurrence->getKey(),
                    'starts_at' => $occurrence->starts_at->toIso8601String(),
                    'ends_at' => $occurrence->ends_at?->toIso8601String(),
                    'capacity' => $occurrence->effectiveCapacity(),
                    'status' => $occurrence->status->value,
                ])->values()->all(),
            'speakers' => $event->speakers->map(fn ($speaker) => [
                'name' => $speaker->name,
                'bio' => $speaker->bio,
                'organisation' => $speaker->organisation,
                'photo_url' => $this->mediaUrl($speaker->photo),
                'social_links' => $speaker->social_links,
                'role' => $speaker->pivot?->getAttribute('role'),
            ])->values()->all(),
            'sponsors' => $event->sponsors->map(fn ($sponsor) => [
                'name' => $sponsor->name,
                'url' => $sponsor->url,
                'tier' => $sponsor->tier,
                'logo_url' => $this->mediaUrl($sponsor->logo),
            ])->values()->all(),
            'ticket_types' => $event->ticketTypes
                ->filter(fn ($ticketType) => $ticketType->is_active)
                ->map(fn ($ticketType) => [
                    'id' => $ticketType->getKey(),
                    'name' => $ticketType->name,
                    'description' => $ticketType->description,
                    'price' => $ticketType->base_price,
                    'currency' => $ticketType->currency,
                ])->values()->all(),
            'registration_questions' => $event->allRegistrationQuestions()->get()->map(fn ($question) => [
                'id' => $question->getKey(),
                'label' => $question->label,
                'type' => $question->type,
                'options' => $question->options,
                'is_required' => $question->is_required,
            ])->values()->all(),
            'checkout_url' => url(trim((string) config('ticketing.routes.api_prefix', 'api/ticketing'), '/')."/events/{$event->getKey()}/checkout"),
        ];
    }

    protected function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk((string) config('ticketing.storage.media_disk', 'public'))->url($path);
    }
}
