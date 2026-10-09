import { mount } from '@vue/test-utils';

const TODAY = '2026-06-15';
const YESTERDAY = '2026-06-14';

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
                'mt-icon': true,
                'router-link': true,
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

        expect(wrapper.findAll('.sw-dashboard-metrics__card')).toHaveLength(3);
        expect(wrapper.vm.metricCards.map((card) => card.key)).toEqual([
            'revenue',
            'orders',
            'averageOrderValue',
        ]);
    });

    it('adds the new customer metric with customer permission', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.sw-dashboard-metrics__card')).toHaveLength(4);
        expect(wrapper.vm.metricCards.at(-1).key).toBe('newCustomers');
    });

    it('uses paid orders for revenue and all orders for the order count', async () => {
        statisticResponse = {
            paid: [{ date: TODAY, count: 2, amount: 100 }],
            all: [{ date: TODAY, count: 5, amount: 400 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.currentMetrics).toEqual({
            revenue: 100,
            paidOrders: 2,
            orders: 5,
            newCustomers: 0,
        });
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

        expect(wrapper.vm.currentMetrics.revenue).toBe(60);
        expect(wrapper.vm.currentMetrics.orders).toBe(7);
        expect(wrapper.vm.previousMetrics.revenue).toBe(900);
    });

    it('calculates the average order value from paid orders only', async () => {
        statisticResponse = {
            paid: [{ date: TODAY, count: 4, amount: 100 }],
            all: [{ date: TODAY, count: 9, amount: 900 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.averageOrderValue).toBe(25);
    });

    it('reports zero average order value when nothing was paid', async () => {
        statisticResponse = {
            paid: [],
            all: [{ date: TODAY, count: 3, amount: 300 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.averageOrderValue).toBe(0);
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

        expect(wrapper.vm.previousMetrics).toEqual({
            revenue: 100,
            paidOrders: 2,
            orders: 2,
            newCustomers: 0,
        });

        const ordersCard = wrapper.vm.metricCards.find((card) => card.key === 'orders');
        expect(ordersCard.delta).toEqual({
            direction: 'up',
            text: '↑ 100.0% sw-dashboard.metrics.comparedToYesterday',
        });
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

        expect(wrapper.vm.metricCards.every((card) => card.delta === null)).toBe(true);
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

            const revenueCard = wrapper.vm.metricCards.find((card) => card.key === 'revenue');
            const filters = JSON.parse(decodeURIComponent(revenueCard.link.query['grid.filter.order']));

            expect(revenueCard.link.name).toBe('sw.order.index');
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

            const ordersCard = wrapper.vm.metricCards.find((card) => card.key === 'orders');
            const filters = JSON.parse(decodeURIComponent(ordersCard.link.query['grid.filter.order']));

            expect(Object.keys(filters)).toEqual(['order-date-filter']);
        });

        it('links the day filter to the local calendar day of the user', async () => {
            Shopwell.Store.get('session').setCurrentUser({ timeZone: 'Asia/Shanghai' });
            jest.setSystemTime(new Date('2026-06-15T18:00:00.000Z'));

            const wrapper = await createWrapper();
            await flushPromises();

            const ordersCard = wrapper.vm.metricCards.find((card) => card.key === 'orders');
            const filters = JSON.parse(decodeURIComponent(ordersCard.link.query['grid.filter.order']));

            // 18:00 UTC is already the 16th of June in Shanghai
            expect(filters['order-date-filter'].criteria[0].parameters).toEqual({
                gte: '2026-06-15T16:00:00.000Z',
                lte: '2026-06-16T15:59:59.000Z',
            });
        });

        it('keeps the customer metric without a link', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const customerCard = wrapper.vm.metricCards.find((card) => card.key === 'newCustomers');
            expect(customerCard.link).toBeNull();
        });
    });
});
