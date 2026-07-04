<x-filament-panels::page>
    <div class="atx-reports">
        <style>
            .atx-reports { display: grid; gap: 1.25rem; }
            .atx-reports__filters {
                display: flex; flex-wrap: wrap; align-items: flex-end; gap: .9rem;
                background: #fff; border-radius: .75rem; padding: 1rem 1.25rem;
                box-shadow: 0 1px 2px rgb(0 0 0 / .06), 0 0 0 1px rgb(0 0 0 / .05);
            }
            .dark .atx-reports__filters { background: #18181b; box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
            .atx-reports__field { display: grid; gap: .3rem; font-size: .8rem; font-weight: 600; }
            .atx-reports__field select,
            .atx-reports__field input {
                min-width: 11rem; font-size: .875rem; font-weight: 400;
                border: 1px solid #d4d4d8; border-radius: .5rem; padding: .45rem .65rem;
                background: #fff; color: inherit;
            }
            .dark .atx-reports__field select, .dark .atx-reports__field input { background: #27272a; border-color: #3f3f46; }
            .atx-reports__spacer { flex: 1 1 auto; }
            .atx-reports__card {
                background: #fff; border-radius: .75rem; overflow: hidden;
                box-shadow: 0 1px 2px rgb(0 0 0 / .06), 0 0 0 1px rgb(0 0 0 / .05);
            }
            .dark .atx-reports__card { background: #18181b; box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
            .atx-reports__scroll { overflow-x: auto; }
            .atx-reports__table { width: 100%; border-collapse: collapse; font-size: .875rem; }
            .atx-reports__table th {
                text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
                padding: .8rem 1rem; border-bottom: 1px solid rgb(0 0 0 / .08); white-space: nowrap; opacity: .75;
            }
            .dark .atx-reports__table th { border-color: rgb(255 255 255 / .1); }
            .atx-reports__table td { padding: .6rem 1rem; border-bottom: 1px solid rgb(0 0 0 / .04); white-space: nowrap; }
            .dark .atx-reports__table td { border-color: rgb(255 255 255 / .06); }
            .atx-reports__table tbody tr:nth-child(even) td { background: rgb(0 0 0 / .02); }
            .dark .atx-reports__table tbody tr:nth-child(even) td { background: rgb(255 255 255 / .02); }
            .atx-reports__table td.is-answers { white-space: normal; min-width: 16rem; }
            .atx-reports__empty { padding: 2.5rem 1rem; text-align: center; opacity: .6; }
            .atx-reports__count { font-size: .8rem; opacity: .6; padding: .6rem 1rem; }
        </style>

        <div class="atx-reports__filters">
            <label class="atx-reports__field">
                <span>Report</span>
                <select wire:model.live="report">
                    @foreach ($this->reportOptions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="atx-reports__field">
                <span>Event</span>
                <select wire:model.live="eventId">
                    <option value="">All events</option>
                    @foreach ($this->eventOptions() as $id => $title)
                        <option value="{{ $id }}">{{ $title }}</option>
                    @endforeach
                </select>
            </label>

            <label class="atx-reports__field">
                <span>Order status</span>
                <select wire:model.live="status">
                    @foreach ($this->statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="atx-reports__field">
                <span>From</span>
                <input type="date" wire:model.live="from">
            </label>

            <label class="atx-reports__field">
                <span>Until</span>
                <input type="date" wire:model.live="until">
            </label>

            <span class="atx-reports__spacer"></span>

            <x-filament::button wire:click="export" icon="heroicon-o-arrow-down-tray">
                Export CSV
            </x-filament::button>
        </div>

        @php
            $headers = $this->headers();
            $rows = $this->rows();
            $answersColumn = array_search('Answers', $headers, true);
        @endphp

        <div class="atx-reports__card">
            <div class="atx-reports__scroll">
                <table class="atx-reports__table">
                    <thead>
                        <tr>
                            @foreach ($headers as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                @foreach ($row as $index => $cell)
                                    <td @class(['is-answers' => $index === $answersColumn])>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($headers) }}">
                                    <div class="atx-reports__empty">No data for the selected filters.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows !== [])
                <div class="atx-reports__count">{{ count($rows) }} row(s) — the CSV export contains exactly this data.</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
