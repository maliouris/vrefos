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
