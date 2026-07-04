<x-filament-panels::page>
    <div
        wire:ignore
        class="atx-scan"
        id="atx-checkin-root"
        data-scan-url="{{ route('ticketing.checkin.scan', ['token' => '__TOKEN__']) }}"
        data-stats-url="{{ route('ticketing.checkin.stats', ['occurrence' => '__ID__']) }}"
        data-csrf="{{ csrf_token() }}"
    >
        <style>
            .atx-scan { display: grid; gap: 1.25rem; }
            @media (min-width: 1024px) { .atx-scan { grid-template-columns: minmax(0, 420px) minmax(0, 1fr); align-items: start; } }
            .atx-scan__card {
                background: #fff; border-radius: .75rem; padding: 1.1rem 1.25rem;
                box-shadow: 0 1px 2px rgb(0 0 0 / .06), 0 0 0 1px rgb(0 0 0 / .05);
            }
            .dark .atx-scan__card { background: #18181b; box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
            .atx-scan__col { display: grid; gap: 1.25rem; }
            .atx-scan__pickers { display: grid; gap: .75rem; }
            .atx-scan__label { display: grid; gap: .3rem; font-size: .8rem; font-weight: 600; }
            .atx-scan select, .atx-scan input[type="text"], .atx-scan input[type="search"] {
                width: 100%; font-size: .875rem; font-weight: 400;
                border: 1px solid #d4d4d8; border-radius: .5rem; padding: .5rem .65rem;
                background: #fff; color: inherit;
            }
            .dark .atx-scan select, .dark .atx-scan input[type="text"], .dark .atx-scan input[type="search"] { background: #27272a; border-color: #3f3f46; }
            #atx-qr-reader { width: 100%; max-width: 320px; margin: .75rem auto 0; border-radius: .75rem; overflow: hidden; background: #0a0a0a; }
            #atx-qr-reader video { width: 100% !important; height: auto !important; display: block; object-fit: cover; }
            #atx-qr-reader img { display: none; } /* library's default info icon */
            .atx-scan__hint { margin: .6rem 0 0; text-align: center; font-size: .75rem; opacity: .6; }
            .atx-scan__camera-tools { display: flex; justify-content: center; margin-top: .6rem; }
            #atx-camera-flip {
                display: none; align-items: center; gap: .4rem; font-size: .78rem; font-weight: 600;
                border: 1px solid #d4d4d8; border-radius: 999px; padding: .3rem .85rem;
                background: transparent; color: inherit; cursor: pointer;
            }
            .dark #atx-camera-flip { border-color: #3f3f46; }
            #atx-camera-flip.is-available { display: inline-flex; }
            .atx-scan__manual { display: flex; gap: .5rem; margin-top: 1rem; }
            .atx-scan__stats { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
            .atx-scan__stat { text-align: center; }
            .atx-scan__stat-number { font-size: 2.4rem; font-weight: 800; line-height: 1.1; }
            .atx-scan__stat-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; opacity: .6; }
            .atx-scan__result { display: none; border-radius: .75rem; padding: 1.4rem 1.25rem; text-align: center; }
            .atx-scan__result.is-visible { display: block; }
            .atx-scan__result-status { font-size: 1.5rem; font-weight: 800; }
            .atx-scan__result-name { font-size: 1.1rem; margin-top: .25rem; }
            .atx-scan__result-detail { font-size: .85rem; margin-top: .25rem; opacity: .85; }
            .atx-scan__log { list-style: none; margin: .5rem 0 0; padding: 0; font-size: .85rem; display: grid; gap: .3rem; }
            .atx-scan__log li { padding: .35rem .6rem; border-radius: .4rem; background: rgb(0 0 0 / .03); }
            .dark .atx-scan__log li { background: rgb(255 255 255 / .05); }
            .atx-scan__card h3 { margin: 0; font-size: .9rem; font-weight: 700; }
        </style>

        <div class="atx-scan__col">
            <div class="atx-scan__card">
                <div class="atx-scan__pickers">
                    <label class="atx-scan__label">
                        <span>Event</span>
                        <input
                            type="search"
                            id="atx-event-search"
                            placeholder="Search events…"
                            autocomplete="off"
                        >
                        <select id="atx-event"></select>
                    </label>
                    <label class="atx-scan__label">
                        <span>Date</span>
                        <select id="atx-occurrence"></select>
                    </label>
                </div>
                <script type="application/json" id="atx-events-data">@json($this->scannerEvents())</script>

                <div id="atx-qr-reader"></div>
                <div class="atx-scan__camera-tools">
                    <button type="button" id="atx-camera-flip" title="Switch camera">⟲ Switch camera</button>
                </div>
                <p class="atx-scan__hint">
                    Point the camera at a ticket QR code.
                </p>

                <form id="atx-manual-form" class="atx-scan__manual">
                    <input id="atx-manual-token" type="text" placeholder="Or type/paste a ticket token…" autocomplete="off">
                    <x-filament::button type="submit">Check in</x-filament::button>
                </form>
            </div>
        </div>

        <div class="atx-scan__col">
            <div class="atx-scan__card atx-scan__stats">
                <div class="atx-scan__stat">
                    <div class="atx-scan__stat-number" id="atx-stat-checked-in">–</div>
                    <div class="atx-scan__stat-label">Checked in</div>
                </div>
                <div class="atx-scan__stat">
                    <div class="atx-scan__stat-number" id="atx-stat-total">–</div>
                    <div class="atx-scan__stat-label">Attendees</div>
                </div>
            </div>

            <div id="atx-result" class="atx-scan__result">
                <div class="atx-scan__result-status" id="atx-result-status"></div>
                <div class="atx-scan__result-name" id="atx-result-name"></div>
                <div class="atx-scan__result-detail" id="atx-result-detail"></div>
            </div>

            <div class="atx-scan__card">
                <h3>Recent scans</h3>
                <ul id="atx-scan-log" class="atx-scan__log"></ul>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        (function () {
            const root = document.getElementById('atx-checkin-root');
            const scanUrlTemplate = root.dataset.scanUrl;
            const statsUrlTemplate = root.dataset.statsUrl;
            const csrf = root.dataset.csrf;

            const occurrenceSelect = document.getElementById('atx-occurrence');
            const eventSelect = document.getElementById('atx-event');
            const eventSearch = document.getElementById('atx-event-search');
            const eventsData = JSON.parse(document.getElementById('atx-events-data').textContent || '[]');
            const resultBox = document.getElementById('atx-result');
            const resultStatus = document.getElementById('atx-result-status');
            const resultName = document.getElementById('atx-result-name');
            const resultDetail = document.getElementById('atx-result-detail');
            const scanLog = document.getElementById('atx-scan-log');

            let busy = false;
            let lastToken = null;
            let lastTokenAt = 0;

            function refreshStats() {
                const id = occurrenceSelect.value;
                if (!id) {
                    document.getElementById('atx-stat-checked-in').textContent = '–';
                    document.getElementById('atx-stat-total').textContent = '–';
                    return;
                }
                fetch(statsUrlTemplate.replace('__ID__', id), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                    .then((r) => (r.ok ? r.json() : null))
                    .then((data) => {
                        if (!data) return;
                        document.getElementById('atx-stat-checked-in').textContent = data.checked_in;
                        document.getElementById('atx-stat-total').textContent = data.total;
                    })
                    .catch(() => {});
            }

            function showResult(kind, title, name, detail) {
                const palette = {
                    success: 'background-color:#dcfce7;color:#14532d;',
                    warning: 'background-color:#fef9c3;color:#713f12;',
                    error: 'background-color:#fee2e2;color:#7f1d1d;',
                };
                resultBox.classList.add('is-visible');
                resultBox.style.cssText = palette[kind] || '';
                resultStatus.textContent = title;
                resultName.textContent = name || '';
                resultDetail.textContent = detail || '';

                const li = document.createElement('li');
                li.textContent = new Date().toLocaleTimeString() + ' — ' + title + (name ? ' — ' + name : '');
                scanLog.prepend(li);
                while (scanLog.children.length > 10) scanLog.removeChild(scanLog.lastChild);
            }

            function submitToken(token) {
                token = (token || '').trim();
                if (!token || busy) return;
                if (!updateDayGate()) return;
                const nowMs = Date.now();
                if (token === lastToken && nowMs - lastTokenAt < 3000) return;
                lastToken = token;
                lastTokenAt = nowMs;
                busy = true;

                fetch(scanUrlTemplate.replace('__TOKEN__', encodeURIComponent(token)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ source: 'filament-scanner' }),
                })
                    .then((r) => r.json().then((data) => ({ status: r.status, data })))
                    .then(({ data, status }) => {
                        const attendee = data.attendee || {};
                        if (data.status === 'checked_in') {
                            showResult('success', '✓ Checked in', attendee.name, attendee.ticket_type);
                        } else if (data.status === 'already_checked_in') {
                            showResult('warning', 'Already checked in', attendee.name, data.message);
                        } else if (data.status === 'not_paid') {
                            showResult('error', 'Order not paid', attendee.name, data.message);
                        } else if (data.status === 'expired') {
                            showResult('error', 'Ticket expired', attendee.name, data.message);
                        } else if (status === 404) {
                            showResult('error', 'Unknown ticket', null, 'This QR code does not match any attendee.');
                        } else {
                            showResult('error', 'Error', null, data.message || 'Unexpected response.');
                        }
                        refreshStats();
                    })
                    .catch(() => showResult('error', 'Network error', null, 'Could not reach the server.'))
                    .finally(() => {
                        busy = false;
                    });
            }

            // ── Two-step picker: search events, then choose a date ─────────
            function currentEvent() {
                return eventsData.find((e) => String(e.id) === eventSelect.value) || null;
            }

            function selectedOccurrence() {
                const ev = currentEvent();
                if (!ev) return null;
                return ev.occurrences.find((o) => String(o.id) === occurrenceSelect.value) || null;
            }

            // Check-in is only valid on the day of the event. Off-day selections
            // show a standing warning and are blocked before hitting the server.
            function updateDayGate() {
                const o = selectedOccurrence();
                if (o && !o.is_today) {
                    showResult('error', 'Event not on the day', null,
                        'This event is not happening today — tickets can only be checked in on the event date.');
                    return false;
                }
                return true;
            }

            function populateOccurrences(ev) {
                occurrenceSelect.innerHTML = '';

                if (!ev) {
                    occurrenceSelect.appendChild(new Option(eventsData.length ? 'Select an event first…' : 'No upcoming events', ''));
                    refreshStats();
                    return;
                }

                ev.occurrences.forEach((o) => occurrenceSelect.appendChild(new Option(o.label, o.id)));

                if (ev.more_count > 0) {
                    const more = new Option('… and ' + ev.more_count + ' more date' + (ev.more_count === 1 ? '' : 's'), '');
                    more.disabled = true;
                    occurrenceSelect.appendChild(more);
                }

                // Preselect today's date for door staff; otherwise the first shown date.
                const today = ev.occurrences.find((o) => o.is_today);
                occurrenceSelect.value = String((today || ev.occurrences[0]).id);
                updateDayGate();
                refreshStats();
            }

            function renderEvents(filter) {
                const query = (filter || '').trim().toLowerCase();
                const matches = eventsData.filter((e) => !query || e.title.toLowerCase().includes(query));

                eventSelect.innerHTML = '';

                if (!matches.length) {
                    eventSelect.appendChild(new Option(eventsData.length ? 'No matching events' : 'No upcoming events', ''));
                    populateOccurrences(null);
                    return;
                }

                if (matches.length > 1) {
                    eventSelect.appendChild(new Option('Select an event… (' + matches.length + ')', ''));
                }

                matches.forEach((e) => eventSelect.appendChild(new Option(e.title, e.id)));

                // A single match (or a search narrowing to one) selects itself.
                if (matches.length === 1) {
                    eventSelect.value = String(matches[0].id);
                }

                populateOccurrences(currentEvent());
            }

            eventSearch.addEventListener('input', () => renderEvents(eventSearch.value));
            eventSelect.addEventListener('change', () => populateOccurrences(currentEvent()));
            occurrenceSelect.addEventListener('change', () => { updateDayGate(); refreshStats(); });
            renderEvents('');
            setInterval(refreshStats, 15000);

            document.getElementById('atx-manual-form').addEventListener('submit', function (e) {
                e.preventDefault();
                const input = document.getElementById('atx-manual-token');
                submitToken(input.value);
                input.value = '';
            });

            if (window.Html5Qrcode) {
                const scanner = new Html5Qrcode('atx-qr-reader');
                const flipButton = document.getElementById('atx-camera-flip');
                const scanConfig = { fps: 10, qrbox: { width: 200, height: 200 }, aspectRatio: 1.0 };
                let cameras = [];
                let cameraIndex = -1;
                let switching = false;

                function showCameraError() {
                    document.getElementById('atx-qr-reader').innerHTML =
                        '<p style="padding:1rem;text-align:center;font-size:0.8rem;color:#fca5a5;">Camera unavailable — use the manual token field below.</p>';
                }

                function startWith(source) {
                    return scanner.start(source, scanConfig, (decodedText) => submitToken(decodedText), () => {});
                }

                // Prefer the rear camera; a square viewport keeps the preview compact.
                startWith({ facingMode: 'environment' }).catch(showCameraError);

                Html5Qrcode.getCameras()
                    .then((found) => {
                        cameras = found || [];
                        if (cameras.length > 1) {
                            flipButton.classList.add('is-available');
                        }
                    })
                    .catch(() => {});

                flipButton.addEventListener('click', function () {
                    if (!cameras.length || switching) return;
                    switching = true;
                    cameraIndex = (cameraIndex + 1) % cameras.length;

                    scanner
                        .stop()
                        .then(() => startWith(cameras[cameraIndex].id))
                        .catch(showCameraError)
                        .finally(() => {
                            switching = false;
                        });
                });
            }
        })();
    </script>
    {{-- Door-staff mode: start with the sidebar collapsed (the toggle in the
         top bar reopens it). Requires sidebarCollapsibleOnDesktop() on the panel. --}}
    <script>
        (function () {
            var collapseSidebar = function () {
                var sidebar = window.Alpine && window.Alpine.store('sidebar');

                if (sidebar && sidebar.isOpen) {
                    sidebar.close();
                }
            };

            if (window.Alpine) {
                collapseSidebar();
            } else {
                document.addEventListener('alpine:initialized', collapseSidebar, { once: true });
            }
        })();
    </script>
</x-filament-panels::page>
