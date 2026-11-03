import { mount } from '@vue/test-utils';

const TODAY = '2026-06-15';
const YESTERDAY = '2026-06-14';

const LABEL = {
    revenue: 'sw-dashboard.metrics.revenue',
    orders: 'sw-dashboard.metrics.orders',
    averageOrderValue: 'sw-dashboard.metrics.averageOrderValue',
    newCustomers: 'sw-dashboard.metrics.newCustomers',
    walletBalance: 'sw-dashboard.metrics.walletBalance',
    monthlyPoints: 'sw-dashboard.metrics.monthlyPoints',
};

let statisticResponse = {
    paid: [],
    all: [],
};

function createRepositoryFactory(overrides = {}) {
    const repositories = {
        order: {
            buildHeaders: () => ({ Authorization: 'Bearer test' }),
        },
        customer: {
            search: jest.fn().mockResolvedValue({ total: 0 }),
        },
        state_machine_state: {
            search: jest.fn().mockResolvedValue([]),
        },
        ...overrides,
    };

    return {
        create: (entity) => repositories[entity],
        repositories,
    };
}

async function createWrapper(privileges = ['order.viewer', 'customer.viewer'], factory = createRepositoryFactory()) {
    return mount(await wrapTestComponent('sw-dashboard-metrics', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: [
                        'title',
                        'subtitle',
                        'helpText',
                        'isLoading',
                        'positionIdentifier',
                    ],
                },
                // the link target is what the operator gets on a click, so the stub writes it onto the
                // anchor instead of dropping it
                'router-link': {
                    props: ['to'],
                    template: '<a class="router-link" :data-to="JSON.stringify(to)"><slot /></a>',
                },
                'mt-icon': true,
                'sw-extension-component-section': true,
            },
            mocks: {
                $t: (key) => key,
            },
            provide: {
                repositoryFactory: factory,
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
    });
}

function cardLabels(wrapper) {
    return wrapper.findAll('.sw-dashboard-metrics__label').map((label) => label.text());
}

function cardByLabel(wrapper, label) {
    return wrapper.findAll('.sw-dashboard-metrics__card').find((card) => {
        return card.find('.sw-dashboard-metrics__label').text() === label;
    });
}

function valueOf(wrapper, label) {
    return cardByLabel(wrapper, label).find('.sw-dashboard-metrics__value').text();
}

function deltaOf(wrapper, label) {
    const delta = cardByLabel(wrapper, label).find('.sw-dashboard-metrics__delta');

    return delta.exists() ? delta.text() : null;
}

function linkOf(wrapper, label) {
    // a linked card *is* the anchor, so the target is on the card element itself
    const to = cardByLabel(wrapper, label).attributes('data-to');

    return to === undefined ? null : JSON.parse(to);
}

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/component/sw-dashboard-metrics', () => {
    beforeAll(() => {
        Shopwell.Context.app.systemCurrencyISOCode = 'EUR';

        Shopwell.Application.addInitializer('httpClient', () => {
            return {
                get: (url) =>
                    Promise.resolve({
                        data: {
                            statistic: url.includes('paid=true') ? statisticResponse.paid : statisticResponse.all,
                        },
                    }),
            };
        });

        jest.useFakeTimers('modern');
    });

    beforeEach(() => {
        jest.setSystemTime(new Date(`${TODAY}T10:00:00.000Z`));
        Shopwell.Store.get('session').setCurrentUser({});
        statisticResponse = { paid: [], all: [] };
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('does not render the metrics without order permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-metrics__grid').exists()).toBe(false);
    });

    it('renders three metrics without customer permission', async () => {
        const wrapper = await createWrapper(['order.viewer']);
        await flushPromises();

        expect(cardLabels(wrapper)).toEqual([
            LABEL.revenue,
            LABEL.orders,
            LABEL.averageOrderValue,
        ]);
    });

    it('adds the new customer metric with customer permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(cardLabels(wrapper)).toEqual([
            LABEL.revenue,
            LABEL.orders,
            LABEL.averageOrderValue,
            LABEL.newCustomers,
        ]);
    });

    it('adds the wallet balance card with wallet permission', async () => {
        const walletSearch = jest.fn().mockResolvedValue({
            aggregations: { walletBalance: { sum: 1234.5 } },
        });
        const factory = createRepositoryFactory({
            customer_wallet: { search: walletSearch },
        });

        const wrapper = await createWrapper(['order.viewer', 'wallet.viewer'], factory);
        await flushPromises();

        expect(valueOf(wrapper, LABEL.walletBalance)).toContain('1,234.50');
        // a balance has no "yesterday", so the card prints no comparison at all
        expect(deltaOf(wrapper, LABEL.walletBalance)).toBeNull();
        expect(linkOf(wrapper, LABEL.walletBalance).name).toBe('sw.finance.walletTransactions');
        expect(walletSearch).toHaveBeenCalledTimes(1);
    });

    it('adds the monthly points card with loyalty permission', async () => {
        const pointsSearch = jest.fn().mockResolvedValue({
            aggregations: { monthlyPoints: { sum: 4200 } },
        });
        const factory = createRepositoryFactory({
            loyalty_point_transaction: { search: pointsSearch },
        });

        const wrapper = await createWrapper(['order.viewer', 'loyalty.viewer'], factory);
        await flushPromises();

        expect(valueOf(wrapper, LABEL.monthlyPoints)).toBe('4,200 sw-dashboard.metrics.pointsUnit');
        expect(deltaOf(wrapper, LABEL.monthlyPoints)).toBeNull();
        expect(linkOf(wrapper, LABEL.monthlyPoints).name).toBe('sw.settings.membership.purchases');
    });

    it('hides the stock cards without their permissions', async () => {
        const wrapper = await createWrapper(['order.viewer', 'customer.viewer']);
        await flushPromises();

        expect(cardLabels(wrapper)).not.toContain(LABEL.walletBalance);
        expect(cardLabels(wrapper)).not.toContain(LABEL.monthlyPoints);
    });

    it('uses paid orders for revenue and all orders for the order count', async () => {
        statisticResponse = {
            paid: [{ date: TODAY, count: 2, amount: 100 }],
            all: [{ date: TODAY, count: 5, amount: 400 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(valueOf(wrapper, LABEL.revenue)).toContain('100');
        expect(valueOf(wrapper, LABEL.orders)).toContain('5');
        expect(valueOf(wrapper, LABEL.averageOrderValue)).toContain('50');
    });

    it('matches the bucket of the local calendar day', async () => {
        Shopwell.Store.get('session').setCurrentUser({ timeZone: 'Asia/Shanghai' });
        jest.setSystemTime(new Date('2026-06-15T18:00:00.000Z'));

        // 18:00 UTC is already the 16th of June in Shanghai, so the buckets of the
        // 15th are the previous day and must not be used for the current metrics.
        statisticResponse = {
            paid: [
                { date: '2026-06-16', count: 3, amount: 60 },
                { date: '2026-06-15', count: 9, amount: 900 },
            ],
            all: [{ date: '2026-06-16', count: 7, amount: 999 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(valueOf(wrapper, LABEL.revenue)).toContain('60');
        expect(valueOf(wrapper, LABEL.orders)).toContain('7');
        // 900 on the day before is what the delta is measured against
        expect(deltaOf(wrapper, LABEL.revenue)).toContain('93.3%');
    });

    it('calculates the average order value from paid orders only', async () => {
        statisticResponse = {
            paid: [{ date: TODAY, count: 4, amount: 100 }],
            all: [{ date: TODAY, count: 9, amount: 900 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        // 100 paid over 4 paid orders, not over the 9 orders that exist
        expect(valueOf(wrapper, LABEL.averageOrderValue)).toContain('25');
    });

    it('reports zero average order value when nothing was paid', async () => {
        statisticResponse = {
            paid: [],
            all: [{ date: TODAY, count: 3, amount: 300 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(valueOf(wrapper, LABEL.averageOrderValue)).toContain('0');
    });

    it('compares the metrics with the previous day', async () => {
        statisticResponse = {
            paid: [
                { date: TODAY, count: 2, amount: 120 },
                { date: YESTERDAY, count: 2, amount: 100 },
            ],
            all: [
                { date: TODAY, count: 4, amount: 400 },
                { date: YESTERDAY, count: 2, amount: 200 },
            ],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(deltaOf(wrapper, LABEL.orders)).toBe('↑ 100.0% sw-dashboard.metrics.comparedToYesterday');
    });

    it('counts the new customers of today and yesterday in separate ranges', async () => {
        const customerSearch = jest.fn().mockResolvedValue({ total: 0 });
        const factory = createRepositoryFactory({
            customer: { search: customerSearch },
        });

        await createWrapper(['order.viewer', 'customer.viewer'], factory);
        await flushPromises();

        expect(customerSearch).toHaveBeenCalledTimes(2);

        const ranges = customerSearch.mock.calls.map(([criteria]) => criteria.filters[0].parameters);

        // today: everything since midnight
        expect(ranges[0]).toEqual({ gte: expect.any(String) });
        // yesterday: must be bounded by the start of today, otherwise the
        // comparison would include today's customers as well
        expect(ranges[1]).toEqual({ gte: expect.any(String), lte: ranges[0].gte });
    });

    it('omits the comparison when the previous day has no data', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.sw-dashboard-metrics__delta.is-up')).toHaveLength(0);
        expect(wrapper.findAll('.sw-dashboard-metrics__delta.is-down')).toHaveLength(0);
        expect(wrapper.findAll('.sw-dashboard-metrics__delta.is-none').length).toBeGreaterThan(0);
    });

    describe('deep links', () => {
        it('links paid metrics to the paid orders of the current day', async () => {
            const factory = createRepositoryFactory({
                state_machine_state: {
                    search: jest.fn().mockResolvedValue([{ id: 'paid-state-id', name: 'Paid' }]),
                },
            });

            const wrapper = await createWrapper(['order.viewer'], factory);
            await flushPromises();

            const link = linkOf(wrapper, LABEL.revenue);
            const filters = JSON.parse(decodeURIComponent(link.query['grid.filter.order']));

            expect(link.name).toBe('sw.order.index');
            expect(filters['order-date-filter'].criteria[0].field).toBe('orderDateTime');
            expect(filters['payment-status-filter'].criteria[0]).toEqual({
                type: 'equalsAny',
                field: 'primaryOrderTransaction.stateMachineState.id',
                value: 'paid-state-id',
            });
        });

        it('links the order count to the orders of the current day without a payment filter', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const link = linkOf(wrapper, LABEL.orders);
            const filters = JSON.parse(decodeURIComponent(link.query['grid.filter.order']));

            expect(Object.keys(filters)).toEqual(['order-date-filter']);
        });

        it('links the day filter to the local calendar day of the user', async () => {
            Shopwell.Store.get('session').setCurrentUser({ timeZone: 'Asia/Shanghai' });
            jest.setSystemTime(new Date('2026-06-15T18:00:00.000Z'));

            const wrapper = await createWrapper();
            await flushPromises();

            const link = linkOf(wrapper, LABEL.orders);
            const filters = JSON.parse(decodeURIComponent(link.query['grid.filter.order']));

            // 18:00 UTC is already the 16th of June in Shanghai
            expect(filters['order-date-filter'].criteria[0].parameters).toEqual({
                gte: '2026-06-15T16:00:00.000Z',
                lte: '2026-06-16T15:59:59.000Z',
            });
        });

        it('keeps the customer metric without a link', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(linkOf(wrapper, LABEL.newCustomers)).toBeNull();
            expect(cardByLabel(wrapper, LABEL.newCustomers).classes()).not.toContain('is-linked');
        });
    });
});
