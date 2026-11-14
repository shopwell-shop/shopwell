/** @sw-package framework */
function date(value?: string | null): Date | null {
    const result = value ? new Date(value) : null;
    return result && Number.isFinite(result.getTime()) ? result : null;
}
/** @private */
export function relativeTime(value?: string | null, now = Date.now()): string {
    const timestamp = date(value);
    if (!timestamp) return '';
    const minutes = Math.round((timestamp.getTime() - now) / 60000);
    const unit = Math.abs(minutes) < 60 ? 'minute' : Math.abs(minutes) < 1440 ? 'hour' : 'day';
    const divisor = unit === 'minute' ? 1 : unit === 'hour' ? 60 : 1440;
    return new Intl.RelativeTimeFormat(Shopwell.Store.get('session').currentLocale || 'en', { numeric: 'auto' }).format(
        Math.round(minutes / divisor),
        unit,
    );
}
/** @private */
export function absoluteTime(value?: string | null): string {
    const timestamp = date(value);
    return timestamp
        ? new Intl.DateTimeFormat(Shopwell.Store.get('session').currentLocale || 'en', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(timestamp)
        : '';
}
/** @private */
export function initialOf(name?: string | null): string {
    return name?.trim().slice(0, 1) ?? '';
}
