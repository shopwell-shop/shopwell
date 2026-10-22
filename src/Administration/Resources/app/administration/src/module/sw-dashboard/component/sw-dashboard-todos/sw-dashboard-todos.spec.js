import { mount } from '@vue/test-utils';

function createRepositoryFactory(overrides = {}) {
    const repositories = {
        order: {
            search: jest.fn().mockResolvedValue({ total: 0 }),
        },
        order_return: {
            search: jest.fn().mockResolvedValue({ total: 0 }),
        },
        product_review: {
            search: jest.fn().mockResolvedValue({ total: 0 }),
        },
        product_warehouse: {
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

async function createWrapper(privileges = ['order.viewer'], factory = createRepositoryFactory()) {
    return mount(await wrapTestComponent('sw-dashboard-todos', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'mt-icon': true,
                'mt-badge': {
                    template: '<span class="mt-badge"><slot></slot></span>',
                    props: ['variant'],
                },
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
describe('module/sw-dashboard/component/sw-dashboard-todos', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({});
    });

    it('shows the empty state when every counter is zero', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-todos__empty').exists()).toBe(true);
        expect(wrapper.find('.sw-dashboard-todos__list').exists()).toBe(false);
    });

    it('lists only the non-zero counters with their counters', async () => {
        const factory = createRepositoryFactory({
            order_return: {
                search: jest.fn().mockResolvedValue({ total: 3 }),
            },
            order: {
                search: jest.fn().mockResolvedValue({ total: 12 }),
            },
            state_machine_state: {
                search: jest.fn().mockResolvedValue([{ id: 'open-state', name: 'Open' }]),
            },
        });

        const wrapper = await createWrapper(['order.viewer', 'order_return.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.items.map((item) => item.key)).toEqual(['returnRequests', 'ordersToShip']);
        expect(wrapper.vm.items[0].count).toBe(3);
        expect(wrapper.vm.items[1].count).toBe(12);
        expect(wrapper.find('.sw-dashboard-todos__empty').exists()).toBe(false);
    });

    it('hides the review counter without review permission', async () => {
        const factory = createRepositoryFactory({
            product_review: {
                search: jest.fn().mockResolvedValue({ total: 8 }),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.items.map((item) => item.key)).toEqual([]);
    });

    it('shows the review counter with review permission', async () => {
        const factory = createRepositoryFactory({
            product_review: {
                search: jest.fn().mockResolvedValue({ total: 8 }),
            },
        });

        const wrapper = await createWrapper(['order.viewer', 'review.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.items.map((item) => item.key)).toEqual(['reviewsWaiting']);
    });

    it('counts the out of stock rows with inventory permission', async () => {
        const factory = createRepositoryFactory({
            product_warehouse: {
                search: jest.fn().mockResolvedValue({ total: 5 }),
            },
        });

        const wrapper = await createWrapper(['order.viewer', 'inventory.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.items.map((item) => item.key)).toEqual(['outOfStock']);
    });

    it('links the ship todo to the order list filtered by open deliveries', async () => {
        const factory = createRepositoryFactory({
            order: {
                search: jest.fn().mockResolvedValue({ total: 2 }),
            },
            state_machine_state: {
                search: jest.fn().mockResolvedValue([{ id: 'open-state', name: 'Open' }]),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        const shipItem = wrapper.vm.items.find((item) => item.key === 'ordersToShip');
        const filters = JSON.parse(decodeURIComponent(shipItem.link.query['grid.filter.order']));

        expect(shipItem.link.name).toBe('sw.order.index');
        expect(filters['delivery-status-filter'].criteria[0]).toEqual({
            type: 'equalsAny',
            field: 'primaryOrderDelivery.stateMachineState.id',
            value: 'open-state',
        });
    });

    it('links the return todo to the return list', async () => {
        const factory = createRepositoryFactory({
            order_return: {
                search: jest.fn().mockResolvedValue({ total: 1 }),
            },
        });

        const wrapper = await createWrapper(['order.viewer', 'order_return.viewer'], factory);
        await flushPromises();

        const returnItem = wrapper.vm.items.find((item) => item.key === 'returnRequests');
        expect(returnItem.link.name).toBe('sw.order.return.list');
    });
});
