import { config, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

/**
 * @sw-package checkout
 */

const mockItem = {
    orderNumber: '1',
    orderCustomer: {
        customerId: '2',
        name: 'Ada',
    },
    currency: {
        isoCode: 'EUR',
    },
    stateMachineState: {
        translated: { name: 'Open' },
        name: 'Open',
    },
    salesChannel: {
        name: 'Test',
    },
    primaryOrderTransaction: {
        stateMachineState: {
            technicalName: 'open',
            name: 'Open',
            translated: { name: 'Open' },
        },
    },
    primaryOrderDelivery: {
        stateMachineState: {
            technicalName: 'open',
            name: 'Open',
            translated: { name: 'Open' },
        },
        shippingOrderAddress: {
            street: '123 Random street',
            zipcode: '12345',
            city: 'Random City',
        },
    },
    billingAddress: {
        street: '123 Random street',
        zipcode: '12345',
        city: 'Random City',
    },
};

if (!Shopwell.Feature.isActive('v6.8.0.0')) {
    mockItem.addresses = [
        {
            street: '123 Random street',
        },
    ];
    mockItem.transactions = new EntityCollection(null, null, null, new Criteria(1, 25), [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' },
            },
        },
    ]);
    mockItem.deliveries = [
        {
            stateMachineState: {
                technicalName: 'open',
                name: 'Open',
                translated: { name: 'Open' },
            },
        },
    ];
}

function mountWithRouter(component, options) {
    const mocks = config.global.mocks;
    config.global.mocks = Object.fromEntries(Object.entries(mocks).filter(([key]) => !['$route', '$router'].includes(key)));
    try {
        return mount(component, options);
    } finally {
        config.global.mocks = mocks;
    }
}

async function createWrapper({ orders = [], term = '', searchFields = { name: 500 } } = {}) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                path: '/',
                name: 'sw.order.index',
                component: { template: '<div/>' },
                meta: { $module: { icon: 'regular-content' } },
            },
            { path: '/profile', name: 'sw.profile.index.searchPreferences', component: { template: '<div/>' } },
            { path: '/bulk/:excludeDelivery', name: 'sw.bulk.edit.order', component: { template: '<div/>' } },
        ],
    });
    await router.push({ name: 'sw.order.index', query: { page: 1, limit: 25, term } });
    const search = jest.fn(() =>
        Promise.resolve(
            Object.assign(
                orders.map((order, index) => ({ id: `order-${index}`, ...order })),
                { total: orders.length },
            ),
        ),
    );
    const ranking = {
        getSearchFieldsByEntity: jest.fn(() => Promise.resolve(searchFields)),
        buildSearchQueriesForEntity: jest.fn((_fields, _term, criteria) => criteria),
        isValidTerm: (value) => value && value.trim().length >= 1,
    };
    const wrapper = mountWithRouter(await wrapTestComponent('sw-order-list', { sync: true }), {
        global: {
            plugins: [router],
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"/><slot name="content"/><slot name="sidebar"/></div>',
                },
                'sw-label': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-pagination': true,
                'sw-data-grid-settings': {
                    props: ['columns'],
                    template: `<button class="show-delivery-address" @click="$emit('change-column-visibility', true, columns.findIndex(column => column.property === 'primaryOrderDelivery.shippingOrderAddress.street'))">Show address</button>`,
                },
                'router-link': { template: '<a><slot/></a>' },
                'sw-checkbox-field': true,
                'sw-data-grid-skeleton': true,
                'sw-time-ago': true,
                'sw-color-badge': true,
                'sw-search-bar': true,
                'sw-language-switch': true,
                'sw-bulk-edit-modal': {
                    template: `<button class="confirm-bulk-edit" @click="$emit('edit-items')">Edit</button>`,
                },
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': {
                    name: 'sw-sidebar-filter-panel',
                    props: ['filters'],
                    template: `<button class="apply-filter" @click="$emit('criteria-changed', [{ type: 'equals', field: 'foo', value: 'bar' }])">Filter</button>`,
                },
                'sw-sidebar': { template: '<aside><slot/></aside>' },
                'sw-data-grid-column-boolean': true,
                'sw-data-grid-inline-edit': true,
                'sw-provide': { template: '<slot/>', inheritAttrs: false },
            },
            provide: {
                stateStyleDataProviderService: { getStyle: () => ({ variant: 'success' }) },
                repositoryFactory: { create: () => ({ search }) },
                filterFactory: {
                    create: (_entity, filters) => Object.entries(filters).map(([name, filter]) => ({ name, ...filter })),
                },
                searchRankingService: ranking,
            },
        },
    });
    await flushPromises();
    return { wrapper, search, ranking, router };
}

function createTransactionCollection(transactions) {
    return new EntityCollection(null, null, null, new Criteria(1, 25), transactions);
}

Shopwell.Service().register('filterService', () => ({ mergeWithStoredFilters: (_storeKey, criteria) => criteria }));

const state = (technicalName, name) => ({ stateMachineState: { technicalName, translated: { name } } });
const address = (street, zipcode, city) => ({ street, zipcode, city });

async function renderOrder(order, showAddress = false) {
    const result = await createWrapper({ orders: [{ ...mockItem, ...order }] });
    if (showAddress) await result.wrapper.get('.show-delivery-address').trigger('click');
    return result;
}

describe('src/module/sw-order/page/sw-order-list', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it.each([
        [[], true],
        [['order.creator'], false],
    ])('should control the add button by %s', async (privileges, disabled) => {
        global.activeAclRoles = privileges;
        const { wrapper } = await createWrapper();
        expect(wrapper.get('.sw-order-list__add-order').attributes('disabled') !== undefined).toBe(disabled);
    });

    it('should contain the manual label only for an order created by an administrator', async () => {
        const { wrapper } = await createWrapper({ orders: [{ ...mockItem, createdById: '1' }, { ...mockItem }] });
        expect(wrapper.get('.sw-data-grid__row--0').find('.sw-order-list__manual-order-label').exists()).toBe(true);
        expect(wrapper.get('.sw-data-grid__row--1').find('.sw-order-list__manual-order-label').exists()).toBe(false);
    });

    it('should render comment tooltips for the available comments', async () => {
        const { wrapper } = await createWrapper({
            orders: [
                { ...mockItem, customerComment: 'Customer comment', internalComment: 'Internal comment' },
                { ...mockItem, customerComment: 'Customer comment', internalComment: null },
                { ...mockItem, customerComment: null, internalComment: 'Internal comment' },
            ],
        });
        const buttons = [0, 1, 2].map((index) =>
            wrapper.get(`.sw-data-grid__row--${index}`).findAll('.sw-order-list__tooltip-order-comment'),
        );
        expect(buttons.map((row) => row.length)).toEqual([2, 1, 1]);
        expect(buttons[0][0].classes()).toContain('mt-button--secondary');
        expect(buttons[0][1].classes()).toContain('mt-button--primary');
        expect(buttons[1][0].classes()).toContain('mt-button--secondary');
        expect(buttons[2][0].classes()).toContain('mt-button--primary');
    });

    it('should render an empty customer cell when the order has no customer', async () => {
        const warning = jest.spyOn(console, 'warn').mockImplementation();
        const { wrapper } = await createWrapper({
            orders: [
                { ...mockItem, orderCustomer: { customerId: '1', name: 'foo bar' } },
                { ...mockItem, orderCustomer: null },
            ],
        });
        expect(warning).toHaveBeenCalledWith('[[sw-data-grid] Can not resolve accessor: orderCustomer.name]');
        expect(wrapper.get('.sw-data-grid__row--0 .sw-data-grid__cell--orderCustomer-name').text()).toBe('foo bar');
        expect(wrapper.get('.sw-data-grid__row--1 .sw-data-grid__cell--orderCustomer-name').text()).toBe('');
    });

    it('should add query score to the repository request', async () => {
        const { ranking } = await createWrapper({ term: 'foo' });
        expect(ranking.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(ranking.buildSearchQueriesForEntity).toHaveBeenCalledWith({ name: 500 }, 'foo', expect.any(Criteria));
    });

    it('should not query ranking fields for an empty search term', async () => {
        const { ranking } = await createWrapper();
        expect(ranking.getSearchFieldsByEntity).not.toHaveBeenCalled();
        expect(ranking.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('should not build query scores without configured ranking fields', async () => {
        const { ranking } = await createWrapper({ term: 'foo', searchFields: {} });
        expect(ranking.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(ranking.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('should show the empty state for an unsearchable entity', async () => {
        const { wrapper, ranking, search } = await createWrapper({ term: 'foo', searchFields: {} });
        expect(ranking.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(search).not.toHaveBeenCalled();
        expect(wrapper.get('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.findComponent({ name: 'sw-data-grid' }).exists()).toBe(false);
    });

    it('should show the payment status from the primary transaction', async () => {
        const { wrapper } = await renderOrder({ primaryOrderTransaction: state('paid', 'Paid') });
        expect(wrapper.get('.sw-data-grid__cell--primaryOrderTransaction-stateMachineState-name').text()).toBe('Paid');
    });

    it('should navigate to bulk editing with the delivery exclusion of selected rows', async () => {
        global.activeAclRoles = ['order.editor'];
        const { wrapper, router } = await renderOrder({ primaryOrderDelivery: null, deliveries: [] });
        const push = jest.spyOn(router, 'push');
        await wrapper.get('.sw-data-grid__select-all input').setValue(true);
        await wrapper.get('.sw-data-grid__bulk-selected a').trigger('click');
        await wrapper.get('.confirm-bulk-edit').trigger('click');
        await flushPromises();
        expect(push).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'sw.bulk.edit.order',
                params: expect.objectContaining({ excludeDelivery: '1' }),
            }),
        );
    });

    it('should load the required order associations with a page of 25', async () => {
        const { search } = await createWrapper();
        const criteria = search.mock.calls[0][0];
        expect(criteria.getLimit()).toBe(25);
        [
            'billingAddress',
            'salesChannel',
            'orderCustomer',
            'currency',
            'documents',
            'stateMachineState',
            'primaryOrderTransaction',
            'primaryOrderDelivery',
        ].forEach((association) => expect(criteria.hasAssociation(association)).toBe(true));
        expect(criteria.getAssociation('primaryOrderDelivery').hasAssociation('stateMachineState')).toBe(true);
        expect(criteria.getAssociation('primaryOrderDelivery').hasAssociation('shippingOrderAddress')).toBe(true);
        expect(criteria.getAssociation('primaryOrderTransaction').hasAssociation('stateMachineState')).toBe(true);
    });

    it.deprecated('v6.8.0.0')('should sort fallback deliveries like the detail page', async () => {
        const { search } = await createWrapper();
        expect(search.mock.calls[0][0].getAssociation('deliveries').sortings).toEqual([
            expect.objectContaining({ field: 'shippingCosts.unitPrice', order: 'DESC' }),
            expect.objectContaining({ field: 'id', order: 'ASC' }),
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should load only primary order associations', async () => {
        const { search } = await createWrapper();
        const criteria = search.mock.calls[0][0];
        expect(criteria.hasAssociation('primaryOrderDelivery')).toBe(true);
        expect(criteria.hasAssociation('primaryOrderTransaction')).toBe(true);
        ['deliveries', 'transactions', 'addresses'].forEach((association) =>
            expect(criteria.hasAssociation(association)).toBe(false),
        );
    });

    it('should offer affiliate, campaign and promotion code filters to the sidebar', async () => {
        const { wrapper } = await createWrapper();
        const filters = wrapper.getComponent({ name: 'sw-sidebar-filter-panel' }).props('filters');
        [
            ['affiliate-code-filter', 'affiliateCode', 'affiliateCodeFilter'],
            ['campaign-code-filter', 'campaignCode', 'campaignCodeFilter'],
            ['promotion-code-filter', 'lineItems.payload.code', 'promotionCodeFilter'],
        ].forEach(([name, property, snippet]) =>
            expect(filters).toContainEqual(
                expect.objectContaining({
                    name,
                    property,
                    type: 'string-filter',
                    label: `sw-order.filters.${snippet}.label`,
                    placeholder: `sw-order.filters.${snippet}.placeholder`,
                    valueProperty: 'key',
                    labelProperty: 'key',
                    ...(name === 'promotion-code-filter'
                        ? { additionalFilters: [Criteria.equals('lineItems.type', 'promotion')] }
                        : {}),
                }),
            ),
        );
    });

    it('should request filtered orders after the sidebar changes', async () => {
        const { wrapper, search } = await createWrapper();
        await wrapper.get('.apply-filter').trigger('click');
        await flushPromises();
        expect(search.mock.calls.at(-1)[0].filters).toContainEqual(Criteria.equals('foo', 'bar'));
    });

    it('should render the primary delivery address when its column is enabled', async () => {
        const { wrapper } = await renderOrder(
            {
                primaryOrderDelivery: {
                    ...state('open', 'Open'),
                    shippingOrderAddress: address('742 Evergreen Terrace', '99999', 'Springfield'),
                },
            },
            true,
        );
        ['742 Evergreen Terrace', 'Springfield', '99999'].forEach((value) => expect(wrapper.html()).toContain(value));
    });

    it('should hide the delivery address column by default', async () => {
        const { wrapper } = await renderOrder({});
        const column = wrapper
            .getComponent('.sw-data-grid')
            .props('columns')
            .find((item) => item.property === 'primaryOrderDelivery.shippingOrderAddress.street');
        expect(column).toEqual(expect.objectContaining({ visible: false }));
        expect(wrapper.html()).not.toContain('123 Random street');
    });

    it('should render the primary delivery state', async () => {
        const { wrapper } = await renderOrder({
            primaryOrderDelivery: {
                ...state('shipped', 'Shipped'),
                shippingOrderAddress: mockItem.primaryOrderDelivery.shippingOrderAddress,
            },
        });
        expect(wrapper.findAll('.sw-order-list__state').map((cell) => cell.text())).toContain('Shipped');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should ignore fallback deliveries and transactions', async () => {
        const { wrapper } = await renderOrder({
            primaryOrderDelivery: null,
            primaryOrderTransaction: null,
            deliveries: [state('shipped', 'Fallback Shipped')],
            transactions: createTransactionCollection([state('paid', 'Fallback Paid')]),
        });
        expect(wrapper.text()).not.toContain('Fallback Shipped');
        expect(wrapper.text()).not.toContain('Fallback Paid');
    });

    if (!Shopwell.Feature.isActive('v6.8.0.0')) {
        it('should render the first fallback delivery address when no primary delivery exists', async () => {
            const { wrapper } = await renderOrder(
                {
                    primaryOrderDelivery: null,
                    deliveries: [
                        {
                            ...state('open', 'Open'),
                            shippingOrderAddress: address('First Fallback Street', '54321', 'Fallback City'),
                        },
                        {
                            ...state('shipped', 'Shipped'),
                            shippingOrderAddress: address('Second Fallback Street', '22222', 'Second City'),
                        },
                    ],
                },
                true,
            );
            ['First Fallback Street', 'Fallback City', '54321'].forEach((value) => expect(wrapper.html()).toContain(value));
            expect(wrapper.html()).not.toContain('Second Fallback Street');
        });

        it('should render the fallback delivery state', async () => {
            const { wrapper } = await renderOrder({
                primaryOrderDelivery: null,
                deliveries: [
                    {
                        ...state('shipped', 'Fallback Shipped'),
                        shippingOrderAddress: mockItem.primaryOrderDelivery.shippingOrderAddress,
                    },
                ],
            });
            expect(wrapper.findAll('.sw-order-list__state').map((cell) => cell.text())).toContain('Fallback Shipped');
        });

        it.each([
            [[state('paid', 'Fallback Paid')], 'Fallback Paid'],
            [
                [state('cancelled', 'Fallback Cancelled'), state('paid', 'Fallback Paid'), state('open', 'Fallback Open')],
                'Fallback Paid',
            ],
            [[state('cancelled', 'Fallback Cancelled'), state('failed', 'Fallback Failed')], 'Fallback Failed'],
        ])('should choose the eligible fallback transaction (%s)', async (transactions, expected) => {
            const { wrapper } = await renderOrder({
                primaryOrderTransaction: null,
                transactions: createTransactionCollection(transactions),
            });
            const states = wrapper.findAll('.sw-order-list__state').map((cell) => cell.text());
            expect(states).toContain(expected);
            expect(states).not.toContain('Fallback Cancelled');
        });

        it('should render no transaction state when all transactions are missing', async () => {
            const { wrapper } = await renderOrder({ primaryOrderTransaction: null, transactions: [] });
            expect(wrapper.get('.sw-data-grid__cell--primaryOrderTransaction-stateMachineState-name').text()).toBe('');
        });

        it('should render no delivery address when all deliveries are missing', async () => {
            const { wrapper } = await renderOrder({ primaryOrderDelivery: null, deliveries: [] }, true);
            expect(wrapper.html()).not.toContain('123 Random street');
        });
    }
});
