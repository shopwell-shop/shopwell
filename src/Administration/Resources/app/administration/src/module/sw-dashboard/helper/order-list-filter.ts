/* eslint-disable sw-deprecation-rules/private-feature-declarations */
import { zonedTimeToUtc } from 'date-fns-tz';

/**
 * @sw-package after-sales
 *
 * The order list persists its filter state as url encoded JSON inside the
 * `grid.filter.order` query parameter (see `filter.service.js`). Building that
 * payload by hand allows the dashboard to deep link into a pre-filtered order
 * list without touching the order list component itself.
 */

type StoredFilter = {
    value: unknown;
    criteria: unknown[];
};

export type StoredFilters = Record<string, StoredFilter>;

export type StateSelection = {
    id: string;
    name: string;
};

export type DayBounds = {
    from: string;
    to: string;
};

export const ORDER_LIST_STORE_KEY = 'grid.filter.order';

export const ORDER_DATE_FILTER = 'order-date-filter';

export const ORDER_PAYMENT_FILTER = 'payment-status-filter';

export const ORDER_DELIVERY_FILTER = 'delivery-status-filter';

export const ORDER_PAYMENT_PROPERTY = 'primaryOrderTransaction.stateMachineState';

export const ORDER_DELIVERY_PROPERTY = 'primaryOrderDelivery.stateMachineState';

export function getUserTimeZone(): string {
    return (Shopwell.Store.get('session')?.currentUser?.timeZone as string) ?? 'UTC';
}

/**
 * Resolves the local calendar day of the given date the same way
 * `sw-date-filter` does, so stored filter values stay comparable.
 */
export function getUserTimeZoneDate(date: Date): string {
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: getUserTimeZone(),
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });

    const parts = formatter.formatToParts(date);
    const year = parts.find((part) => part.type === 'year')?.value ?? '';
    const month = parts.find((part) => part.type === 'month')?.value ?? '';
    const day = parts.find((part) => part.type === 'day')?.value ?? '';

    return `${year}-${month}-${day}`;
}

/**
 * Start and end of the local calendar day as UTC ISO strings, matching the
 * normalised value `sw-date-filter` emits.
 */
export function getDayBounds(date: Date): DayBounds {
    const timeZone = getUserTimeZone();
    const day = getUserTimeZoneDate(date);

    return {
        from: zonedTimeToUtc(`${day}T00:00:00.000`, timeZone).toISOString(),
        to: zonedTimeToUtc(`${day}T23:59:59.000`, timeZone).toISOString(),
    };
}

/**
 * Local calendar day keys of the current day and the day before.
 *
 * `OrderAmountService` groups orders by `DATE_FORMAT(CONVERT_TZ(order_date_time,
 * '+00:00', :timezone), '%Y-%m-%d')`, so the buckets are keyed by the calendar
 * day in the *user* timezone. Deriving those keys with `toISODate()` would use
 * the UTC day instead and shift every lookup by one day for users east or west
 * of UTC.
 */
export function getDayKeys(reference: Date = new Date()): { today: string; yesterday: string } {
    return {
        today: getUserTimeZoneDate(reference),
        yesterday: getUserTimeZoneDate(getPreviousDay(reference)),
    };
}

export function getPreviousDay(reference: Date = new Date()): Date {
    const previous = new Date(reference.getTime());
    previous.setDate(previous.getDate() - 1);

    return previous;
}

export function createOrderDateFilter(bounds: DayBounds, timeframe = 'today'): StoredFilter {
    return {
        value: {
            from: bounds.from,
            to: bounds.to,
            timeframe,
        },
        criteria: [
            {
                type: 'range',
                field: 'orderDateTime',
                parameters: {
                    gte: bounds.from,
                    lte: bounds.to,
                },
            },
        ],
    };
}

export function createStateFilter(property: string, states: StateSelection[]): StoredFilter | null {
    if (states.length === 0) {
        return null;
    }

    return {
        value: states,
        criteria: [
            {
                type: 'equalsAny',
                field: `${property}.id`,
                value: states.map((state) => state.id).join('|'),
            },
        ],
    };
}

export function buildOrderListLink(filters: StoredFilters): { name: string; query: Record<string, string> } {
    return {
        name: 'sw.order.index',
        query: {
            [ORDER_LIST_STORE_KEY]: encodeURIComponent(JSON.stringify(filters)),
        },
    };
}

/**
 * Convenience wrapper for the most common dashboard use case: a local day
 * range combined with an optional single state machine state.
 */
export function buildFilteredOrderListLink(options: {
    date?: Date;
    state?: { filterName: string; property: string; state: StateSelection | null };
}): { name: string; query: Record<string, string> } {
    const filters: StoredFilters = {};

    if (options.date) {
        filters[ORDER_DATE_FILTER] = createOrderDateFilter(getDayBounds(options.date));
    }

    if (options.state?.state) {
        const filter = createStateFilter(options.state.property, [options.state.state]);

        if (filter) {
            filters[options.state.filterName] = filter;
        }
    }

    return buildOrderListLink(filters);
}
