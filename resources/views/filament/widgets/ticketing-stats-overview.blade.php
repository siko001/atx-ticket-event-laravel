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
    <div class="mb-3 flex items-center gap-2">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Event
        </span>

        <select
            wire:model.live="eventFilter"
            class="rounded-lg border-gray-300 bg-white py-1.5 ps-3 pe-8 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
        >
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
