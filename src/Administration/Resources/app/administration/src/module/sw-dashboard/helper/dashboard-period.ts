/**
 * @sw-package after-sales
 */
import { formatInTimeZone, zonedTimeToUtc } from 'date-fns-tz';

/** @private */
export type DashboardPeriod = { date: string };

function getTimeZone(): string {
    return (Shopwell.Store.get('session').currentUser?.timeZone as string) ?? 'Asia/Shanghai';
}

/** @private */
export function getDashboardPeriod(now = new Date()): DashboardPeriod {
    return { date: formatInTimeZone(now, getTimeZone(), 'yyyy-MM-dd') };
}

/** @private */
export function getPeriodBounds(period: DashboardPeriod): { from: string; to: string } {
    const timeZone = getTimeZone();
    return {
        from: zonedTimeToUtc(`${period.date}T00:00:00.000`, timeZone).toISOString(),
        to: zonedTimeToUtc(`${period.date}T23:59:59.999`, timeZone).toISOString(),
    };
}
