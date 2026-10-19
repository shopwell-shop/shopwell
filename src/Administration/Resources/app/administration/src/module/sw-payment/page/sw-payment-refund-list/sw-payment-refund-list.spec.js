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

const refundStates = [
    { id: 'state-open', name: 'open', technicalName: 'open', translated: { name: '退款中' } },
    { id: 'state-finished', name: 'finished', technicalName: 'finished', translated: { name: '已退款' } },
];

const refunds = [
    {
        id: 'refund-1',
        refundNo: 'R-1',
        outRefundNo: 'OR-1',
        stateId: 'state-finished',
        amount: 500,
        currency: 'CNY',
        refundedAt: '2026-01-03T00:00:00.000Z',
        createdAt: '2026-01-02T03:04:05.000Z',
        paymentOrder: { paymentNo: 'P-1', outTradeNo: 'T-1', channelTechnicalName: 'wechat_h5' },
    },
];

async function createWrapper() {
    const search = jest.fn(() =>
        Promise.resolve(new EntityCollection(null, null, null, new Criteria(1, 25), refunds, refunds.length)),
    );

    const stateSearch = jest.fn(() =>
        Promise.resolve(new EntityCollection(null, null, null, new Criteria(1, 50), refundStates, refundStates.length)),
    );

    const wrapper = mount(await wrapTestComponent('sw-payment-refund-list', { sync: true }), {
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
                    name: 'sw.payment.refund',
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

describe('module/sw-payment/page/sw-payment-refund-list', () => {
    beforeEach(() => {
        global.activeAclRoles = ['payment_refund.viewer'];
    });

    it('should have a default filter for every filter option', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.defaultFilters).toEqual([
            'refund-no-filter',
            'out-refund-no-filter',
            'channel-filter',
            'state-filter',
        ]);

        wrapper.vm.defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });

    it('should keep the search and the sorting in the criteria', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.term = 'R-1';

        const criteria = wrapper.vm.criteria;

        expect(criteria.term).toBe('R-1');
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
        expect(criteria.associations.map((association) => association.association)).toContain('paymentOrder');
    });

    it('should load the refunds into the listing state', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.refunds).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);

        // the states are read once, not with every list request
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    it('should offer the states of the refund machine as filter options', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.stateFilterOptions).toEqual([
            { id: 'state-open', name: '退款中' },
            { id: 'state-finished', name: '已退款' },
        ]);
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(false);

        wrapper.vm.activeFilterNumber = 1;
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('should apply the filters the filter panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        const filters = [Criteria.equalsAny('paymentOrder.channelTechnicalName', ['wechat_h5'])];
        await wrapper.vm.updateCriteria(filters);

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.criteria.filters).toEqual(filters);
        expect(search).toHaveBeenCalled();
    });

    it('should render the state of a refund and the order it belongs to', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.refundStateLabel('state-finished')).toBe('已退款');
        expect(wrapper.vm.refundStateVariant('state-finished')).toBe('positive');
        expect(wrapper.vm.paymentOrderLabel(refunds[0])).toBe('P-1');
        expect(wrapper.vm.paymentOrderLabel({})).toBe('-');
        expect(wrapper.vm.formatAmount(500, 'CNY')).toBe(Shopwell.Utils.format.currency(5, 'CNY', 2));
    });
});
