@php
    $pollingInterval = $this->getPollingInterval();
    $eventOptions = $this->getEventOptions();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class(['fi-wi-stats-overview'])
    "
>
    {{-- Self-contained styles: this widget ships in a package, so it cannot
         rely on the host app's Tailwind build generating utility classes for
         these views. Class-based dark mode matches Filament (html.dark). --}}
    <style>
        .atx-event-filter {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.375rem;
            margin-bottom: 1rem;
        }

        .atx-event-filter__label {
            font-size: 0.875rem;
            font-weight: 500;
            color: rgb(107 114 128); /* gray-500 */
        }

        .atx-event-filter__select {
            width: auto;
            min-width: 12rem;
            border-radius: 0.5rem;
            border: 1px solid rgb(209 213 219); /* gray-300 */
            background-color: #fff;
            padding: 0.375rem 2rem 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
            color: rgb(17 24 39); /* gray-900 */
            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        .dark .atx-event-filter__label {
            color: rgb(156 163 175); /* gray-400 */
        }

        .dark .atx-event-filter__select {
            border-color: rgb(255 255 255 / 0.1);
            background-color: rgb(255 255 255 / 0.05);
            color: #fff;
        }

        /* Per-card metric picker rendered in each Stat's label. */
        .atx-stat-metric {
            cursor: pointer;
            border: 0;
            border-bottom: 1px solid rgb(209 213 219); /* gray-300 */
            border-radius: 0;
            background-color: transparent;
            padding: 0 0.25rem 0.125rem 0;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.25rem;
            color: rgb(107 114 128); /* gray-500 */
        }

        .atx-stat-metric:focus {
            outline: none;
            box-shadow: none;
            border-bottom-color: rgb(107 114 128); /* gray-500 */
        }

        .dark .atx-stat-metric {
            color: rgb(156 163 175); /* gray-400 */
            border-bottom-color: rgb(255 255 255 / 0.2);
        }

        .dark .atx-stat-metric:focus {
            border-bottom-color: rgb(156 163 175); /* gray-400 */
        }
    </style>

    <div class="atx-event-filter">
        <span class="atx-event-filter__label">Event</span>

        <select wire:model.live="eventFilter" class="atx-event-filter__select">
            <option value="" @selected(blank($this->eventFilter))>All events</option>

            @foreach ($eventOptions as $id => $title)
                <option value="{{ $id }}" @selected((string) $this->eventFilter === (string) $id)>
                    {{ $title }}
                </option>
            @endforeach
        </select>
    </div>

    {{ $this->content }}
</x-filament-widgets::widget>
