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
