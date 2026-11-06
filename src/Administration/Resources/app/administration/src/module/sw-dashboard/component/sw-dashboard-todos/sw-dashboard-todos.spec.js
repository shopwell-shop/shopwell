import { shallowMount } from '@vue/test-utils';

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
    return shallowMount(await wrapTestComponent('sw-dashboard-todos', { sync: true }), {
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
                'router-link': {
                    props: ['to'],
                    template: '<a :data-route="to.name" :data-query="JSON.stringify(to.query)"><slot /></a>',
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

        expect(wrapper.findAll('.sw-dashboard-todos__item-label').map((label) => label.text())).toEqual([
            'sw-dashboard.todos.returnRequests',
            'sw-dashboard.todos.ordersToShip',
        ]);
        expect(wrapper.findAll('.sw-dashboard-todos__item-badge').map((badge) => badge.text())).toEqual(['3', '12']);
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

        expect(wrapper.find('.sw-dashboard-todos__list').exists()).toBe(false);
    });

    it('shows the review counter with review permission', async () => {
        const factory = createRepositoryFactory({
            product_review: {
                search: jest.fn().mockResolvedValue({ total: 8 }),
            },
        });

        const wrapper = await createWrapper(['order.viewer', 'review.viewer'], factory);
        await flushPromises();

        expect(wrapper.findAll('.sw-dashboard-todos__item-label').map((label) => label.text())).toEqual([
            'sw-dashboard.todos.reviewsWaiting',
        ]);
        expect(wrapper.get('.sw-dashboard-todos__item-badge').text()).toBe('8');
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

        const shipLink = wrapper.get('a[data-route="sw.order.index"]');
        const query = JSON.parse(shipLink.attributes('data-query'));
        const filters = JSON.parse(decodeURIComponent(query['grid.filter.order']));
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

        expect(wrapper.find('a[data-route="sw.order.return.list"]').exists()).toBe(true);
    });
});
