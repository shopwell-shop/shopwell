import { shallowMount } from '@vue/test-utils';

jest.mock('vue-echarts', () => ({ __esModule: true, default: { name: 'v-chart' } }));
jest.mock('echarts/core', () => ({ use: jest.fn() }));
jest.mock('echarts/charts', () => ({ PieChart: {} }));
jest.mock('echarts/renderers', () => ({ SVGRenderer: {} }));

function createRepositoryFactory(overrides = {}) {
    const repositories = {
        order: {
            search: jest.fn().mockResolvedValue({ aggregations: {} }),
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

async function createWrapper(privileges = ['order.viewer'], factory = createRepositoryFactory()) {
    return shallowMount(await wrapTestComponent('sw-dashboard-order-states', { sync: true }), {
        global: {
            stubs: {
                'mt-empty-state': {
                    props: { headline: String, description: String, icon: String, centered: Boolean },
                    template: '<div class="mt-empty-state">{{ headline }}</div>',
                },
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'v-chart': { props: ['option', 'autoresize', 'initOptions'], template: '<div class="v-chart" />' },
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
describe('module/sw-dashboard/component/sw-dashboard-order-states', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({});
    });

    it('does not render without order permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.find('.mt-card').exists()).toBe(false);
    });

    it('renders the empty state without buckets', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.getComponent('.mt-empty-state').props('headline')).toBe('sw-dashboard.orderStates.empty');
        expect(wrapper.getComponent('.mt-empty-state').props('centered')).toBe(true);
        expect(wrapper.find('.sw-dashboard-order-states__chart').exists()).toBe(false);
    });

    it('translates the state buckets with the names of the order state machine', async () => {
        const factory = createRepositoryFactory({
            order: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        orderStates: {
                            buckets: [
                                { key: 'open', orderCount: { count: 4 } },
                                { key: 'completed', orderCount: { count: 9 } },
                            ],
                        },
                    },
                }),
            },
            state_machine_state: {
                search: jest.fn().mockResolvedValue([
                    { technicalName: 'open', name: 'Open', translated: { name: 'Offen' } },
                    { technicalName: 'completed', name: 'Completed', translated: { name: 'Abgeschlossen' } },
                ]),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        const rows = wrapper.findAll('.sw-dashboard-order-states__row');
        expect(rows.map((row) => row.find('.sw-dashboard-order-states__name').text())).toEqual(['Offen', 'Abgeschlossen']);
        expect(rows.map((row) => row.find('.sw-dashboard-order-states__count').text())).toEqual(['4', '9']);
        expect(rows.map((row) => row.find('.sw-dashboard-order-states__percentage').text())).toEqual(['30.8%', '69.2%']);
        expect(wrapper.find('.sw-dashboard-order-states__empty').exists()).toBe(false);
        expect(wrapper.find('.sw-chart').exists()).toBe(false);
        const chart = wrapper.getComponent('.v-chart');
        expect(chart.props('initOptions').renderer).toBe('svg');
        const series = chart.props('option').series[0];
        expect(series.type).toBe('pie');
        expect(series.data.map(({ name, value }) => ({ name, value }))).toEqual([
            { name: 'Offen', value: 4 },
            { name: 'Abgeschlossen', value: 9 },
        ]);
        expect(series.label.position).toBe('outside');
        expect(series.label.formatter({ name: 'Offen', value: 4, percent: 30.8 })).toContain('30.8%');
        expect(series.labelLine.show).toBe(true);
        expect(wrapper.find('.sw-dashboard-order-states__connector').exists()).toBe(false);
        expect(new Set(wrapper.findAll('.sw-dashboard-order-states__dot').map((dot) => dot.attributes('style'))).size).toBe(
            2,
        );
    });

    it('keeps small slices labelled outside without overlapping callouts', async () => {
        const factory = createRepositoryFactory({
            order: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        orderStates: {
                            buckets: [
                                { key: 'open', orderCount: { count: 97 } },
                                { key: 'in_progress', orderCount: { count: 1 } },
                                { key: 'completed', orderCount: { count: 1 } },
                                { key: 'cancelled', orderCount: { count: 1 } },
                            ],
                        },
                    },
                }),
            },
        });
        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        const series = wrapper.getComponent('.v-chart').props('option').series[0];
        expect(series.data.map((state) => state.value)).toEqual([
            97,
            1,
            1,
            1,
        ]);
        expect(series.avoidLabelOverlap).toBe(true);
        expect(series.label.show).toBe(true);
        expect(series.label.position).toBe('outside');
    });

    it('aggregates only today in the user timezone', async () => {
        const orderSearch = jest.fn().mockResolvedValue({ aggregations: {} });
        const factory = createRepositoryFactory({ order: { search: orderSearch } });

        await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(orderSearch).toHaveBeenCalledTimes(1);

        const criteria = orderSearch.mock.calls[0][0];
        expect(criteria.aggregations).toHaveLength(1);
        expect(wrapperPeriod(criteria.filters[0])).toBeLessThanOrEqual(86400000);
    });

    it('falls back to the technical name when the state is unknown', async () => {
        const factory = createRepositoryFactory({
            order: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        orderStates: {
                            buckets: [{ key: 'custom_state', orderCount: { count: 1 } }],
                        },
                    },
                }),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(wrapper.get('.sw-dashboard-order-states__name').text()).toBe('custom_state');
    });
});

function wrapperPeriod(filter) {
    return Date.parse(filter.parameters.lte) - Date.parse(filter.parameters.gte);
}
