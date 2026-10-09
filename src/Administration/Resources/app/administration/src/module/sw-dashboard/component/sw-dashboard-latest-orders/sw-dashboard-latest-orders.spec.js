import { mount } from '@vue/test-utils';

const ORDER_SEARCH = jest.fn();

const ORDERS = [
    {
        id: 'order-1',
        orderNumber: '10001',
        orderDateTime: '2026-06-15T09:30:00.000Z',
        amountTotal: 123.45,
        currency: { isoCode: 'EUR' },
        orderCustomer: { firstName: 'Ada', lastName: 'Lovelace' },
        stateMachineState: { technicalName: 'open', name: 'Open' },
    },
    {
        id: 'order-2',
        orderNumber: '10002',
        orderDateTime: '2026-06-14T08:00:00.000Z',
        amountTotal: 42,
        currency: { isoCode: 'EUR' },
        orderCustomer: { firstName: 'Grace', lastName: 'Hopper' },
        stateMachineState: { technicalName: 'cancelled', name: 'Cancelled' },
    },
];

function createRepositoryFactory() {
    return {
        create: (entity) => {
            if (entity === 'order') {
                return {
                    buildHeaders: () => ({ Authorization: 'Bearer test' }),
                    search: ORDER_SEARCH,
                };
            }

            return {};
        },
    };
}

async function createWrapper(privileges = ['order.viewer'], orders = ORDERS) {
    ORDER_SEARCH.mockResolvedValue(orders);

    return mount(await wrapTestComponent('sw-dashboard-latest-orders', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><div class="mt-card__header">' +
                        '<slot name="title"></slot><slot name="headerRight"></slot>' +
                        '</div><slot /></div>',
                    props: [
                        'title',
                        'subtitle',
                        'helpText',
                        'isLoading',
                        'positionIdentifier',
                    ],
                },
                'mt-icon': true,
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="mt-badge" :data-variant="variant"><slot /></span>',
                },
                'sw-entity-listing': {
                    props: [
                        'dataSource',
                        'columns',
                        'sortBy',
                        'sortDirection',
                    ],
                    template: `
                        <table class="sw-entity-listing-stub">
                            <thead>
                                <tr><th v-for="col in columns" :key="col.property">{{ col.label }}</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in dataSource" :key="item.id" :data-id="item.id"></tr>
                            </tbody>
                        </table>`,
                },
                'sw-time-ago': true,
                'sw-context-menu-item': true,
                'router-link': {
                    props: ['to'],
                    template: '<a class="router-link" :data-to="JSON.stringify(to)"><slot /></a>',
                },
            },
            mocks: {
                $t: (key) => key,
            },
            provide: {
                repositoryFactory: createRepositoryFactory(),
                stateStyleDataProviderService: {
                    getStyle: (stateMachine, state) => ({
                        meteorVariant: state === 'cancelled' ? 'critical' : 'neutral',
                    }),
                },
                acl: {
                    can: (identifier) => privileges.includes(identifier),
                },
            },
        },
    });
}

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/component/sw-dashboard-latest-orders', () => {
    beforeEach(() => {
        ORDER_SEARCH.mockClear();
    });

    it('does not render without order permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.find('.mt-card').exists()).toBe(false);
        expect(ORDER_SEARCH).not.toHaveBeenCalled();
    });

    it('loads the latest five orders without requesting a total count', async () => {
        await createWrapper();
        await flushPromises();

        expect(ORDER_SEARCH).toHaveBeenCalledTimes(1);

        const criteria = ORDER_SEARCH.mock.calls[0][0];
        expect(criteria.page).toBe(1);
        expect(criteria.limit).toBe(5);
        expect(criteria.totalCountMode).toBe(0);
        expect(criteria.sortings).toEqual([
            { field: 'orderDateTime', order: 'DESC', naturalSorting: false },
        ]);
        expect(criteria.associations.map((a) => a.association)).toEqual(
            expect.arrayContaining(['orderCustomer', 'currency', 'stateMachineState']),
        );
    });

    it('renders the entity listing with the original column setup', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const listing = wrapper.findComponent('.sw-entity-listing-stub');
        expect(listing.exists()).toBe(true);

        const columns = listing.props('columns');
        expect(columns.map((column) => column.label)).toEqual([
            'sw-order.list.columnOrderNumber',
            'sw-dashboard.latestOrders.columnOrderTime',
            'sw-order.list.columnCustomerName',
            'sw-order.list.columnState',
            'sw-order.list.columnAmount',
        ]);
        expect(columns[0].routerLink).toBe('sw.order.detail');
        expect(columns[4].align).toBe('right');

        expect(listing.props('sortBy')).toBe('orderDateTime');
        expect(listing.props('sortDirection')).toBe('DESC');
        expect(listing.props('dataSource')).toHaveLength(2);
    });

    it('maps the order state to the meteor variant', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const [first, second] = wrapper.vm.orders;

        expect(wrapper.vm.getVariantFromOrderState(first)).toBe('neutral');
        expect(wrapper.vm.getVariantFromOrderState(second)).toBe('critical');
        expect(wrapper.vm.getVariantFromOrderState({ stateMachineState: null })).toBe('neutral');
    });

    it('falls back to an empty customer name without an order customer', async () => {
        const wrapper = await createWrapper(
            ['order.viewer'],
            [
                {
                    id: 'order-3',
                    orderNumber: '10003',
                    orderDateTime: '2026-06-13T08:00:00.000Z',
                    amountTotal: 5,
                },
            ],
        );
        await flushPromises();

        expect(wrapper.vm.getCustomerName(wrapper.vm.orders[0])).toBe('');
    });

    it('does not render a link to the full order list', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-latest-orders__view-all').exists()).toBe(false);
        expect(wrapper.find('.mt-card__header a').exists()).toBe(false);
    });

    it('shows the empty state without orders', async () => {
        const wrapper = await createWrapper(['order.viewer'], []);
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-dashboard-latest-orders__empty').text()).toBe('sw-dashboard.latestOrders.empty');
    });
});
