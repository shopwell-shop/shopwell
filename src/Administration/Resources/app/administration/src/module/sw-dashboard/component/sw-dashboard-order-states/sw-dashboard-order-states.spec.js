import { mount } from '@vue/test-utils';

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
    return mount(await wrapTestComponent('sw-dashboard-order-states', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'sw-chart': {
                    props: ['type', 'series', 'options', 'height'],
                    template: '<div class="sw-chart" :data-type="type"></div>',
                },
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
describe('module/sw-dashboard/component/sw-dashboard-order-states', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({});
    });

    it('does not render without order permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.find('mt-card-stub').exists()).toBe(false);
        expect(wrapper.vm.canViewOrders).toBe(false);
    });

    it('renders the empty state without buckets', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-order-states__empty').exists()).toBe(true);
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

        expect(wrapper.vm.buckets).toEqual([
            { name: 'Offen', count: 4 },
            { name: 'Abgeschlossen', count: 9 },
        ]);
        expect(wrapper.vm.series[0].data).toEqual([{ x: 'Offen', y: 4 }]);
        expect(wrapper.vm.hasData).toBe(true);
    });

    it('aggregates the states of the last 30 days', async () => {
        const orderSearch = jest.fn().mockResolvedValue({ aggregations: {} });
        const factory = createRepositoryFactory({ order: { search: orderSearch } });

        await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(orderSearch).toHaveBeenCalledTimes(1);

        const criteria = orderSearch.mock.calls[0][0];
        expect(criteria.aggregations).toHaveLength(1);
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

        expect(wrapper.vm.buckets[0].name).toBe('custom_state');
    });
});
