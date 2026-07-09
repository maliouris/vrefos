// App initialization

// Datetime values are stored as true UTC instants but entered/displayed in the
// device's local timezone. The webview knows the local offset, so all conversion
// happens here. The "UTC string" form is `YYYY-MM-DD HH:mm` (Laravel parses it as
// UTC because APP_TIMEZONE=UTC); the "local input" form is `YYYY-MM-DDTHH:mm` as
// expected by <input type="datetime-local">.

/**
 * Convert a UTC wall-clock string into a local value for a datetime-local input.
 */
window.utcToLocalInput = (utc) => {
    if (!utc) {
        return '';
    }
    const date = new Date(utc.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) {
        return '';
    }
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
};

/**
 * Convert a local datetime-local value into a UTC wall-clock string to store.
 */
window.localInputToUtc = (local) => {
    if (!local) {
        return '';
    }
    const date = new Date(local); // parsed in the device's local timezone
    if (isNaN(date.getTime())) {
        return '';
    }
    return date.toISOString().slice(0, 16).replace('T', ' ');
};

/**
 * Auto-open the OS notification permission dialog, called via x-init on the
 * permission banner (which only renders when permission isn't granted).
 *
 * Deliberately NOT a Livewire request: a failed HTTP call at app cold start
 * would pop Livewire's error modal. This posts straight to NativePHP's bridge
 * endpoint (same contract as the notifications plugin's own JS), swallows all
 * failures, and only marks the sessionStorage guard on success so a cold-start
 * hiccup retries on the next page visit. The dialog's outcome flows back
 * through the plugin's native events into the Livewire banner.
 */
window.autoRequestNotificationPermission = () => {
    const guard = 'notification-permission-prompted';
    if (sessionStorage.getItem(guard)) {
        return;
    }
    // Give the native runtime a moment to settle after a cold start.
    setTimeout(() => {
        fetch('/_native/api/call', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ method: 'LocalNotifications.RequestPermission', params: {} }),
        })
            .then((response) => {
                if (response.ok) {
                    sessionStorage.setItem(guard, '1');
                }
            })
            .catch(() => {});
    }, 800);
};

/**
 * Format a UTC wall-clock string for display in the device's local timezone.
 */
window.formatLocalDateTime = (utc) => {
    if (!utc) {
        return '—';
    }
    const date = new Date(utc.replace(' ', 'T') + 'Z');
    if (isNaN(date.getTime())) {
        return '—';
    }
    return date.toLocaleString([], {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Open/close the sidebar drawer with a horizontal swipe.
 *
 * The drawer is daisyUI's CSS-only pattern: toggling the hidden #main-drawer
 * checkbox is all it takes. A rightward flick starting anywhere on the content
 * opens it, a leftward flick closes it. The swipe may start anywhere rather
 * than at the screen edge because Android's gesture navigation consumes edge
 * touches for the system back gesture — they never reach the webview.
 *
 * Listeners live on `document` because menu links use wire:navigate, which
 * swaps the DOM without re-running this file; the checkbox is looked up lazily
 * at gesture time for the same reason. Listeners are passive and never call
 * preventDefault, so scrolling is unaffected.
 */
(() => {
    const MIN_DISTANCE_X = 60; // px — a deliberate swipe, not a tap
    const HORIZONTAL_DOMINANCE = 1.5; // |dx| must exceed 1.5 × |dy|
    const MAX_DURATION_MS = 600; // a flick, not a slow drag/text selection

    let start = null;

    document.addEventListener('touchstart', (event) => {
        start = null;
        if (event.touches.length > 1) {
            return;
        }
        // Horizontal drags inside scrollable rows or form controls mean
        // scrolling/editing, not a menu gesture.
        if (event.target.closest('.overflow-x-auto, input, textarea, select')) {
            return;
        }
        start = {
            x: event.touches[0].clientX,
            y: event.touches[0].clientY,
            time: Date.now(),
        };
    }, { passive: true });

    document.addEventListener('touchend', (event) => {
        if (!start) {
            return;
        }
        const dx = event.changedTouches[0].clientX - start.x;
        const dy = event.changedTouches[0].clientY - start.y;
        const elapsed = Date.now() - start.time;
        start = null;

        // ≥ lg the sidebar is static (lg:drawer-open) — no drawer to toggle.
        if (window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }
        if (
            Math.abs(dx) < MIN_DISTANCE_X
            || Math.abs(dx) <= HORIZONTAL_DOMINANCE * Math.abs(dy)
            || elapsed > MAX_DURATION_MS
        ) {
            return;
        }

        const drawer = document.getElementById('main-drawer');
        if (!drawer) {
            return;
        }
        if (dx > 0 && !drawer.checked) {
            drawer.checked = true;
        } else if (dx < 0 && drawer.checked) {
            drawer.checked = false;
        }
    }, { passive: true });
})();
