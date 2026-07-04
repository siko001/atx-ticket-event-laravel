{{-- Floating quick-access button for the check-in scanner, injected panel-wide. --}}
@if (! str_contains((string) request()->route()?->getName(), 'check-in-scanner'))
    <a
        href="{{ \AtxDigital\Ticketing\Filament\Pages\CheckInScanner::getUrl() }}"
        title="Check-in scanner"
        aria-label="Open the check-in scanner"
        style="
            position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 40;
            display: flex; align-items: center; justify-content: center;
            width: 3.4rem; height: 3.4rem; border-radius: 9999px;
            background: #f59e0b; color: #451a03;
            box-shadow: 0 10px 20px rgb(0 0 0 / .2), 0 3px 6px rgb(0 0 0 / .12);
            transition: transform .12s ease;
        "
        onmouseover="this.style.transform='scale(1.08)'"
        onmouseout="this.style.transform='scale(1)'"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width: 1.6rem; height: 1.6rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" />
        </svg>
    </a>
@endif
