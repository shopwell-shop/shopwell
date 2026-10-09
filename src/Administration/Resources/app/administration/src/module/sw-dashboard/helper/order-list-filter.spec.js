import {
    ORDER_DELIVERY_FILTER,
    ORDER_DELIVERY_PROPERTY,
    ORDER_LIST_STORE_KEY,
    ORDER_PAYMENT_FILTER,
    ORDER_PAYMENT_PROPERTY,
    buildFilteredOrderListLink,
    buildOrderListLink,
    createOrderDateFilter,
    createStateFilter,
    getDayBounds,
    getDayKeys,
    getPreviousDay,
    getUserTimeZoneDate,
} from './order-list-filter';

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/helper/order-list-filter', () => {
    const reference = new Date('2026-10-02T16:31:00.000Z');

    function setTimeZone(timeZone) {
        Shopwell.Store.get('session').setCurrentUser({ timeZone });
    }

    beforeEach(() => {
        setTimeZone('UTC');
    });

    describe('getUserTimeZoneDate', () => {
        it('returns the utc calendar day for an utc user', () => {
            expect(getUserTimeZoneDate(reference)).toBe('2026-10-02');
        });

        it('returns the local calendar day for a user east of utc', () => {
            setTimeZone('Asia/Shanghai');

            expect(getUserTimeZoneDate(reference)).toBe('2026-10-03');
        });

        it('returns the local calendar day for a user west of utc', () => {
            setTimeZone('America/New_York');

            expect(getUserTimeZoneDate(reference)).toBe('2026-10-02');

            expect(getUserTimeZoneDate(new Date('2026-10-02T02:00:00.000Z'))).toBe('2026-10-01');
        });

        it('falls back to utc when no time zone is configured', () => {
            Shopwell.Store.get('session').setCurrentUser({});

            expect(getUserTimeZoneDate(reference)).toBe('2026-10-02');
        });
    });

    describe('getDayBounds', () => {
        it('covers the whole local day of the given instant', () => {
            setTimeZone('Asia/Shanghai');

            expect(getDayBounds(reference)).toEqual({
                from: '2026-10-02T16:00:00.000Z',
                to: '2026-10-03T15:59:59.000Z',
            });
        });

        it('does not shift the day for an utc user', () => {
            expect(getDayBounds(reference)).toEqual({
                from: '2026-10-02T00:00:00.000Z',
                to: '2026-10-02T23:59:59.000Z',
            });
        });
    });

    describe('getDayKeys', () => {
        it('returns the local day and the day before as bucket keys', () => {
            setTimeZone('Asia/Shanghai');

            expect(getDayKeys(reference)).toEqual({
                today: '2026-10-03',
                yesterday: '2026-10-02',
            });
        });

        it('uses the utc day for an utc user', () => {
            expect(getDayKeys(reference)).toEqual({
                today: '2026-10-02',
                yesterday: '2026-10-01',
            });
        });
    });

    describe('getPreviousDay', () => {
        it('returns the calendar day before, without mutating the input', () => {
            const source = new Date('2026-03-01T10:00:00.000Z');
            const previous = getPreviousDay(source);

            expect(previous).not.toBe(source);
            expect(source.toISOString()).toBe('2026-03-01T10:00:00.000Z');
            expect(previous.getUTCDate()).toBe(28);
            expect(previous.getUTCMonth()).toBe(1);
        });
    });

    describe('createOrderDateFilter', () => {
        it('builds the payload sw-filter-panel restores from the url', () => {
            expect(
                createOrderDateFilter(
                    {
                        from: '2026-10-02T00:00:00.000Z',
                        to: '2026-10-02T23:59:59.000Z',
                    },
                    'today',
                ),
            ).toEqual({
                value: {
                    from: '2026-10-02T00:00:00.000Z',
                    to: '2026-10-02T23:59:59.000Z',
                    timeframe: 'today',
                },
                criteria: [
                    {
                        type: 'range',
                        field: 'orderDateTime',
                        parameters: {
                            gte: '2026-10-02T00:00:00.000Z',
                            lte: '2026-10-02T23:59:59.000Z',
                        },
                    },
                ],
            });
        });
    });

    describe('createStateFilter', () => {
        it('builds an equalsAny criteria over the state ids', () => {
            expect(
                createStateFilter(ORDER_PAYMENT_PROPERTY, [
                    { id: 'abc', name: 'Paid' },
                    { id: 'def', name: 'Open' },
                ]),
            ).toEqual({
                value: [
                    { id: 'abc', name: 'Paid' },
                    { id: 'def', name: 'Open' },
                ],
                criteria: [
                    {
                        type: 'equalsAny',
                        field: 'primaryOrderTransaction.stateMachineState.id',
                        value: 'abc|def',
                    },
                ],
            });
        });

        it('returns null when no state is selected', () => {
            expect(createStateFilter(ORDER_DELIVERY_PROPERTY, [])).toBeNull();
        });
    });

    describe('buildOrderListLink', () => {
        it('targets the order list with the encoded filter payload', () => {
            const link = buildOrderListLink({
                'order-date-filter': createOrderDateFilter(
                    {
                        from: '2026-10-02T00:00:00.000Z',
                        to: '2026-10-02T23:59:59.000Z',
                    },
                    'today',
                ),
            });

            expect(link.name).toBe('sw.order.index');
            expect(Object.keys(link.query)).toEqual([ORDER_LIST_STORE_KEY]);
            expect(JSON.parse(decodeURIComponent(link.query[ORDER_LIST_STORE_KEY]))).toEqual({
                'order-date-filter': {
                    value: {
                        from: '2026-10-02T00:00:00.000Z',
                        to: '2026-10-02T23:59:59.000Z',
                        timeframe: 'today',
                    },
                    criteria: [
                        {
                            type: 'range',
                            field: 'orderDateTime',
                            parameters: {
                                gte: '2026-10-02T00:00:00.000Z',
                                lte: '2026-10-02T23:59:59.000Z',
                            },
                        },
                    ],
                },
            });
        });
    });

    describe('buildFilteredOrderListLink', () => {
        it('adds a day filter only when a date is given', () => {
            const link = buildFilteredOrderListLink({ date: reference });
            const filters = JSON.parse(decodeURIComponent(link.query[ORDER_LIST_STORE_KEY]));

            expect(Object.keys(filters)).toEqual(['order-date-filter']);
        });

        it('combines the day filter with the transaction state', () => {
            const link = buildFilteredOrderListLink({
                date: reference,
                state: {
                    filterName: ORDER_PAYMENT_FILTER,
                    property: ORDER_PAYMENT_PROPERTY,
                    state: { id: 'abc', name: 'Paid' },
                },
            });
            const filters = JSON.parse(decodeURIComponent(link.query[ORDER_LIST_STORE_KEY]));

            expect(Object.keys(filters)).toEqual([
                'order-date-filter',
                ORDER_PAYMENT_FILTER,
            ]);
            expect(filters[ORDER_PAYMENT_FILTER].criteria[0].field).toBe('primaryOrderTransaction.stateMachineState.id');
        });

        it('skips the state filter when the state is unknown', () => {
            const link = buildFilteredOrderListLink({
                date: reference,
                state: {
                    filterName: ORDER_DELIVERY_FILTER,
                    property: ORDER_DELIVERY_PROPERTY,
                    state: null,
                },
            });
            const filters = JSON.parse(decodeURIComponent(link.query[ORDER_LIST_STORE_KEY]));

            expect(Object.keys(filters)).toEqual(['order-date-filter']);
        });

        it('produces an empty payload when nothing is filtered', () => {
            const link = buildFilteredOrderListLink({});

            expect(JSON.parse(decodeURIComponent(link.query[ORDER_LIST_STORE_KEY]))).toEqual({});
        });
    });
});
