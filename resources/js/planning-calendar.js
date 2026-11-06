import 'temporal-polyfill/global';
import { createCalendar, createViewDay, createViewMonthAgenda, createViewList } from '@schedule-x/calendar';
import { createEventsServicePlugin } from '@schedule-x/events-service';
import { createCalendarControlsPlugin } from '@schedule-x/calendar-controls';
import { createCurrentTimePlugin } from '@schedule-x/current-time';
import '@schedule-x/theme-default/dist/index.css';
import '../css/planning-calendar.css';

/**
 * Initializes the Schedule-X calendar on the `/planning` page. The element
 * carries `wire:ignore` (Livewire never touches its DOM), so this only ever
 * runs once per page visit via Alpine's `x-init`; a fresh instance is created
 * on every `livewire:navigated` back to the page.
 */
window.initPlanningCalendar = ($wire, el) => {
    if (el._scheduleXCalendar) {
        return;
    }

    const eventsService = createEventsServicePlugin();
    const controls = createCalendarControlsPlugin();
    const timeZone = Temporal.Now.timeZoneId();
    let lastRange = null;

    const timeFormat = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', timeZone });
    const formatTime = (zonedDateTime) => timeFormat.format(new Date(zonedDateTime.epochMilliseconds));
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));

    // Schedule-X v4 only accepts Temporal objects for event start/end (it throws
    // otherwise), while the server sends ISO-8601 strings with an offset.
    const loadEvents = async (range) => {
        // Month agenda's range is a Temporal.PlainDate ("2026-07-09"); the list
        // view's can be a Temporal.ZonedDateTime ("2026-07-09T00:00:00+00:00[UTC]").
        // Only the calendar date matters server-side, so normalize to the first
        // 10 chars either way rather than parse the RFC 9557 bracket suffix in PHP
        // (Carbon chokes on it).
        lastRange = {
            from: range.start.toString().slice(0, 10),
            to: range.end.toString().slice(0, 10),
        };

        const events = await $wire.getEvents(lastRange.from, lastRange.to, timeZone);

        return events.map((event) => {
            const start = Temporal.Instant.from(event.start).toZonedDateTimeISO(timeZone);
            const end = Temporal.Instant.from(event.end).toZonedDateTimeISO(timeZone);

            // `end` is padded server-side so every event is a visible block in the
            // time grid; `hasEnd` says whether there is a real end time to show.
            const times = event.hasEnd ? `${formatTime(start)} – ${formatTime(end)}` : formatTime(start);

            return {
                ...event,
                start,
                end,
                // The list view adds these classes to the row (CSS hides the end time).
                _options: { additionalClasses: event.hasEnd ? [] : ['is-point'] },
                // Month agenda and Day view get their own card content: Schedule-X's
                // default is "date ⋅ start – end" (agenda) and a clipped, padded end (grid).
                _customContent: {
                    monthAgenda: `<div class="sx__month-agenda-event__title">${escapeHtml(event.title)}</div>`
                        + `<div class="planning-event-times">${times}</div>`,
                    // Day view: title above the times in tall blocks; in short ones the
                    // CSS puts the start time in front of the title on one line.
                    timeGrid: '<div class="planning-event-body">'
                        + `<div class="sx__time-grid-event-title">${escapeHtml(event.title)}</div>`
                        + '<div class="planning-event-times">'
                        + `<span class="planning-time-start">${formatTime(start)}</span>`
                        + (event.hasEnd ? `<span class="planning-time-end"> – ${formatTime(end)}</span>` : '')
                        + '</div></div>',
                },
            };
        });
    };

    // Dims the calendar while events load and tells the user when loading fails.
    const fetchEvents = async (range) => {
        el.classList.add('opacity-60', 'transition-opacity');

        try {
            return await loadEvents(range);
        } catch (error) {
            window.toast?.({
                toast: {
                    type: 'error',
                    title: 'Could not load the calendar',
                    description: 'Pull to refresh or try again in a moment.',
                    position: 'toast-top toast-end',
                    icon: '',
                    css: 'alert-error',
                    timeout: 4000,
                },
            });

            throw error;
        } finally {
            el.classList.remove('opacity-60');
        }
    };

    // Re-query the visible range (timer, filter chips, plan edits).
    const reloadEvents = async () => {
        if (!lastRange) {
            return;
        }

        try {
            eventsService.set(await fetchEvents({
                start: Temporal.PlainDate.from(lastRange.from),
                end: Temporal.PlainDate.from(lastRange.to),
            }));
        } catch (error) {
            console.error('Failed to reload planning calendar events', error);
        }
    };

    const GRID_HEIGHT = 2400; // px for 24 h, so a 15 minute event is ~25px tall
    let lastScrolledView = null;

    // The time grid renders a moment after render()/view switches, so retry
    // until its scroll container exists and has overflow to scroll.
    const scrollTimeGridTo = (top, attempts = 30) => {
        const container = el.querySelector('.sx__view-container');

        if (container && container.scrollHeight > container.clientHeight) {
            container.scrollTo({ top, behavior: 'instant' });

            return;
        }

        if (attempts > 0) {
            requestAnimationFrame(() => scrollTimeGridTo(top, attempts - 1));
        }
    };

    // Day view opens at midnight and list view at the 1st of the month; bring
    // the part that matters into view. The time grid is only re-scrolled when
    // the view changes, so stepping between days doesn't jump around.
    const scrollToContext = () => {
        const view = controls.getView();

        if (view === createViewList().name) {
            requestAnimationFrame(() => {
                el.querySelector(`.sx__list-day[data-date="${controls.getDate()}"]`)
                    ?.scrollIntoView({ block: 'start' });
            });
        } else if (view === createViewDay().name && lastScrolledView !== view) {
            const isToday = controls.getDate().toString() === Temporal.Now.plainDateISO(timeZone).toString();
            const hour = isToday ? Math.max(0, Temporal.Now.plainTimeISO(timeZone).hour - 1) : 6;

            scrollTimeGridTo(hour * (GRID_HEIGHT / 24));
        }

        lastScrolledView = view;
    };

    // Overlapped events (Google Calendar style): Schedule-X lays each concurrent
    // event out to the right edge and draws the later ones on top, so an earlier
    // event's text would wrap under the next one. Pad it so the text wraps only
    // in the part that stays visible. Everything is a percentage of the day
    // column (inset-inline-start/width/top/height inline, padding-right resolves against the
    // same containing block), so the units line up.
    const padOverlappedEvents = () => {
        el.querySelectorAll('.sx__time-grid-day').forEach((day) => {
            const events = [...day.querySelectorAll('.sx__time-grid-event')].map((node) => {
                const left = parseFloat(node.style.insetInlineStart) || 0;

                return {
                    node,
                    top: parseFloat(node.style.top),
                    bottom: parseFloat(node.style.top) + parseFloat(node.style.height),
                    left,
                    right: left + parseFloat(node.style.width),
                };
            });

            events.forEach((event) => {
                const covering = events
                    .filter((other) => other !== event
                        && other.left > event.left
                        && other.top < event.bottom
                        && other.bottom > event.top)
                    .map((other) => other.left);

                event.node.style.paddingRight = covering.length
                    ? `${Math.max(0, event.right - Math.min(...covering))}%`
                    : '';
            });
        });
    };

    let padFrame = null;
    const schedulePadding = () => {
        cancelAnimationFrame(padFrame);
        padFrame = requestAnimationFrame(padOverlappedEvents);
    };

    const calendar = createCalendar(
        {
            views: [createViewDay(), createViewMonthAgenda(), createViewList()],
            defaultView: createViewDay().name,
            weekOptions: { gridHeight: GRID_HEIGHT, eventOverlap: true },
            events: [],
            timezone: timeZone,
            // 24-hour times, matching the rest of the app.
            locale: 'en-GB',
            calendars: JSON.parse(el.dataset.calendars || '{}'),
            callbacks: {
                // Unlike onRangeUpdate, fetchEvents also runs for the very first
                // range on render, which is what loads the initial events.
                fetchEvents,
                onRangeUpdate: scrollToContext,
                // Open a details sheet instead of navigating straight away: there is
                // no hover on a phone to read an event hidden under another one.
                onEventClick(calendarEvent) {
                    $wire.showEvent(
                        calendarEvent.id.toString(),
                        calendarEvent.title ?? '',
                        calendarEvent.start.toInstant().toString(),
                        calendarEvent.hasEnd ? calendarEvent.end.toInstant().toString() : null,
                    );
                },
            },
        },
        [eventsService, controls, createCurrentTimePlugin()],
    );

    calendar.render(el);
    el._scheduleXCalendar = calendar;
    scrollToContext();

    // Events are rendered asynchronously after eventsService.set() and on every
    // view/day change, so watch for DOM changes rather than hooking each path.
    const overlapObserver = new MutationObserver(schedulePadding);
    overlapObserver.observe(el, { childList: true, subtree: true });

    // Ongoing actions grow to "now" and planned slots flip done/missed over
    // time, so refresh the visible range every minute.
    const refreshTimer = setInterval(reloadEvents, 60000);

    // The filter chips and plan edits happen in Livewire, outside this
    // wire:ignore'd element, so the server tells us when to re-query.
    // Livewire.on returns its own cleanup; $wire.$on does not.
    const offEventsChanged = Livewire.on('planning-events-changed', reloadEvents);

    document.addEventListener('livewire:navigating', function cleanup() {
        clearInterval(refreshTimer);
        overlapObserver.disconnect();
        if (typeof offEventsChanged === 'function') {
            offEventsChanged();
        }

        el._scheduleXCalendar = null;
        document.removeEventListener('livewire:navigating', cleanup);
    }, { once: true });
};
