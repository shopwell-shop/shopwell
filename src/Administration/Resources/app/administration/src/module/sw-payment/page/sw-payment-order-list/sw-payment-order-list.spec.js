/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

Shopwell.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (storeKey, criteria) => criteria,
    };
});

const orderStates = [
    { id: 'state-open', name: 'opened', technicalName: 'opened', translated: { name: '待支付' } },
    { id: 'state-paid', name: 'paid', technicalName: 'paid', translated: { name: '已支付' } },
];

const orders = [
    {
        id: 'order-1',
        paymentNo: 'P-1',
        outTradeNo: 'T-1',
        stateId: 'state-paid',
        channelTechnicalName: 'alipay_h5',
        amount: 1234,
        currency: 'CNY',
        createdAt: '2026-01-02T03:04:05.000Z',
        paymentApp: { name: '演示应用', technicalName: 'demo' },
    },
];

async function createWrapper() {
    const search = jest.fn(() =>
        Promise.resolve(new EntityCollection(null, null, null, new Criteria(1, 25), orders, orders.length)),
    );

    const stateSearch = jest.fn(() =>
        Promise.resolve(new EntityCollection(null, null, null, new Criteria(1, 50), orderStates, orderStates.length)),
    );

    const wrapper = mount(await wrapTestComponent('sw-payment-order-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                        </div>`,
                },
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-data-grid': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-pagination': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'sw-time-ago': true,
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        return entity === 'state_machine_state' ? { search: stateSearch } : { search };
                    },
                },
                filterFactory: {
                    create: (entity, filters) => Object.values(filters),
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (searchFields, term, criteria) => criteria,
                    isValidTerm: (term) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.payment.index',
                    query: {},
                    meta: { $module: { icon: 'regular-credit-card' } },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, search, stateSearch };
}

describe('module/sw-payment/page/sw-payment-order-list', () => {
    beforeEach(() => {
        global.activeAclRoles = ['payment_order.viewer'];
    });

    it('should have a default filter for every filter option', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.defaultFilters).toEqual([
            'payment-no-filter',
            'out-trade-no-filter',
            'payment-app-filter',
            'provider-filter',
            'channel-filter',
            'state-filter',
        ]);

        wrapper.vm.defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });

    it('should keep the search and the sorting in the criteria', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.term = 'P-1';

        const criteria = wrapper.vm.criteria;

        expect(criteria.term).toBe('P-1');
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
        expect(criteria.associations.map((association) => association.association)).toContain('paymentApp');
    });

    it('should load the orders into the listing state', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.orders).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);

        // the states are read once, not with every list request
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    it('should offer the states of the machine as filter options', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.stateFilterOptions).toEqual([
            { id: 'state-open', name: '待支付' },
            { id: 'state-paid', name: '已支付' },
        ]);
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(false);

        wrapper.vm.activeFilterNumber = 1;
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('should apply the filters the filter panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        const filters = [Criteria.equals('stateId', 'state-paid')];
        await wrapper.vm.updateCriteria(filters);

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.criteria.filters).toEqual(filters);
        expect(search).toHaveBeenCalled();
    });

    it('should render the state of an order and the app it belongs to', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.paymentStateLabel('state-paid')).toBe('已支付');
        expect(wrapper.vm.paymentStateVariant('state-paid')).toBe('positive');
        expect(wrapper.vm.paymentStateVariant('state-open')).toBe('info');
        expect(wrapper.vm.paymentAppLabel(orders[0])).toBe('演示应用');
        expect(wrapper.vm.paymentAppLabel({})).toBe('-');
    });

    it('should render the amount in the major unit of its currency', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.formatAmount(1234, 'CNY')).toBe(Shopwell.Utils.format.currency(12.34, 'CNY', 2));
    });
});
