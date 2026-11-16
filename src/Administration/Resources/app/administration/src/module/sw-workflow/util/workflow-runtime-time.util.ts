/** @sw-package framework */
function parseTimestamp(value?: string | null): Date | null {
    if (!value) {
        return null;
    }

    const timestamp = new Date(value);
    return Number.isFinite(timestamp.getTime()) ? timestamp : null;
}

function relativeTimeUnit(minutes: number): { unit: Intl.RelativeTimeFormatUnit; divisor: number } {
    const distance = Math.abs(minutes);

    if (distance < 60) {
        return { unit: 'minute', divisor: 1 };
    }

    if (distance < 1440) {
        return { unit: 'hour', divisor: 60 };
    }

    return { unit: 'day', divisor: 1440 };
}

/** @private */
export function relativeTime(value?: string | null, now = Date.now()): string {
    const timestamp = parseTimestamp(value);
    if (!timestamp) {
        return '';
    }

    const minutes = Math.round((timestamp.getTime() - now) / 60000);
    const { unit, divisor } = relativeTimeUnit(minutes);

    return new Intl.RelativeTimeFormat(Shopwell.Store.get('session').currentLocale || 'en', { numeric: 'auto' }).format(
        Math.round(minutes / divisor),
        unit,
    );
}

/** @private */
export function absoluteTime(value?: string | null): string {
    const timestamp = parseTimestamp(value);
    if (!timestamp) {
        return '';
    }

    return new Intl.DateTimeFormat(Shopwell.Store.get('session').currentLocale || 'en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(timestamp);
}

/** @private */
export function initialOf(name?: string | null): string {
    return name?.trim().slice(0, 1) ?? '';
}
