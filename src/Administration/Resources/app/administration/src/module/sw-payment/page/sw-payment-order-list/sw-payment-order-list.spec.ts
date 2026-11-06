/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type Criteria from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- double of a framework service, registered once for the file
Shopwell.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (_storeKey: string, criteria: unknown) => criteria,
    };
});

const STATES = [
    { id: 'state-open', name: 'opened', technicalName: 'opened', translated: { name: '待支付' } },
    { id: 'state-paid', name: 'paid', technicalName: 'paid', translated: { name: '已支付' } },
];

/**
 * The term the merchant submits through the search bar.
 */
const SEARCH_TERM = 'P-1';

/**
 * The filter set the panel hands over when the merchant applies one.
 */
const APPLIED_FILTERS = [{ type: 'equals', field: 'stateId', value: 'state-paid' }];

function buildOrders(): Array<Record<string, unknown>> {
    return [
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
}

/**
 * A search result as the repository delivers it: the entities and the total the grid reads.
 */
function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

async function createWrapper(rows: Array<Record<string, unknown>> = buildOrders()): Promise<{
    wrapper: VueWrapper;
    search: jest.Mock;
    stateSearch: jest.Mock;
    router: { push: jest.Mock; replace: jest.Mock };
}> {
    const router = { push: jest.fn(), replace: jest.fn() };
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'payment_order'>>(rows)));

    const stateSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'state_machine_state'>>(STATES)));

    const wrapper = mount(await wrapTestComponent('sw-payment-order-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                        </div>`,
                },
                // the search bar only reports what the merchant submitted, the page routes it
                'sw-search-bar': {
                    name: 'SwSearchBar',
                    emits: ['search'],
                    template: `<button
                        class="search-bar"
                        @click='$emit("search", ${JSON.stringify(SEARCH_TERM)})'
                    ></button>`,
                },
                // the grid is the table itself - the stub renders the cells the page defines for it
                'sw-entity-listing': {
                    name: 'SwEntityListing',
                    props: [
                        'dataSource',
                        'columns',
                        'sortBy',
                        'sortDirection',
                        'isLoading',
                    ],
                    template: `<div class="grid">
                        <div
                            v-for="item in dataSource"
                            :key="item.id"
                            class="row"
                        >
                            <span class="cell-app"><slot name="column-paymentAppId" :item="item"></slot></span>
                            <span class="cell-state"><slot name="column-stateId" :item="item"></slot></span>
                            <span class="cell-amount"><slot name="column-amount" :item="item"></slot></span>
                        </div>
                    </div>`,
                },
                'sw-sidebar': { template: '<div><slot></slot></div>' },
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': {
                    name: 'SwSidebarFilterPanel',
                    props: [
                        'storeKey',
                        'filters',
                        'defaults',
                        'activeFilterNumber',
                    ],
                    emits: ['criteria-changed'],
                    template: `<div class="filter-panel">
                        <button
                            class="apply-filters"
                            @click='$emit("criteria-changed", ${JSON.stringify(APPLIED_FILTERS)})'
                        ></button>
                    </div>`,
                },
                'sw-time-ago': true,
                'mt-empty-state': {
                    name: 'MtEmptyState',
                    props: ['headline', 'description'],
                    template: '<div class="empty-state"></div>',
                },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="badge" :data-variant="variant"><slot></slot></span>',
                },
                'mt-button': true,
                'mt-icon': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => {
                        return entity === 'state_machine_state' ? { search: stateSearch } : { search };
                    },
                },
                filterFactory: {
                    create: (_entity: string, filters: Record<string, unknown>) => Object.values(filters),
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) => criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.payment.index',
                    query: {},
                    meta: { $module: { icon: 'regular-credit-card' } },
                },
                $router: router,
            },
        },
    });

    await flushPromises();

    return { wrapper, search, stateSearch, router };
}

/**
 * The rows of the grid, as the merchant sees them.
 */
function cells(wrapper: VueWrapper, cell: string): string[] {
    return wrapper.findAll(`.${cell}`).map((entry) => entry.text().trim());
}

/**
 * The criteria of the last request the page sent.
 */
function lastCriteria(search: jest.Mock): Criteria {
    const calls = search.mock.calls as Array<[Criteria]>;

    return calls[calls.length - 1][0];
}

/**
 * The route the page navigated to last, the way the listing mixin writes it.
 */
function lastRoute(router: { replace: jest.Mock }): { query?: Record<string, unknown> } {
    const calls = router.replace.mock.calls as Array<[{ query?: Record<string, unknown> }]>;

    return calls[calls.length - 1][0];
}

describe('module/sw-payment/page/sw-payment-order-list', () => {
    beforeEach(() => {
        // the acl service reads the roles of the test account from the global scope
        (globalThis as { activeAclRoles?: string[] }).activeAclRoles = ['payment_order.viewer'];
    });

    /**
     * The panel renders the defaults in the order the merchant sees them, and a stored selection
     * refers to those names.
     */
    it('should hand the panel one filter per filterable column', async () => {
        const { wrapper } = await createWrapper();

        const panel = wrapper.findComponent({ name: 'SwSidebarFilterPanel' });

        expect(panel.props('defaults')).toEqual([
            'payment-no-filter',
            'out-trade-no-filter',
            'payment-app-filter',
            'provider-filter',
            'channel-filter',
            'state-filter',
        ]);
        expect(panel.props('filters')).toHaveLength(6);
    });

    it('should load the orders and the states of the machine once', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(stateSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.findComponent({ name: 'SwEntityListing' }).props('isLoading')).toBe(false);
        expect(cells(wrapper, 'cell-app')).toEqual(['演示应用']);
    });

    /**
     * The search bar does not fetch by itself: it puts the term into the route, and every list of the
     * administration reads its state from there.
     */
    it('should put the term of the search bar into the route', async () => {
        const { wrapper, router } = await createWrapper();

        await wrapper.find('.search-bar').trigger('click');
        await flushPromises();

        expect(lastRoute(router).query).toEqual(expect.objectContaining({ term: SEARCH_TERM }));
    });

    it('should sort by the newest order and carry the app of every row', async () => {
        const { wrapper, search } = await createWrapper();

        const criteria = lastCriteria(search);

        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
        expect(criteria.associations.map((association) => association.association)).toContain('paymentApp');
        expect(wrapper.findComponent({ name: 'SwEntityListing' }).props('sortBy')).toBe('createdAt');
        expect(wrapper.findComponent({ name: 'SwEntityListing' }).props('sortDirection')).toBe('DESC');
    });

    it('should apply the filters the panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        await wrapper.find('.apply-filters').trigger('click');
        await flushPromises();

        expect(search).toHaveBeenCalled();
        expect(lastCriteria(search).filters).toEqual(APPLIED_FILTERS);
    });

    it('should render the state of an order, its amount and the app it belongs to', async () => {
        const { wrapper } = await createWrapper();

        const badge = wrapper.find('.cell-state .badge');

        expect(badge.text()).toBe('已支付');
        expect(badge.attributes('data-variant')).toBe('positive');
        expect(cells(wrapper, 'cell-amount')).toEqual([Shopwell.Utils.format.currency(12.34, 'CNY', 2)]);
        expect(cells(wrapper, 'cell-app')).toEqual(['演示应用']);
    });

    it('should name the states in the filter by their translation', async () => {
        const { wrapper } = await createWrapper();

        const stateFilter = (
            wrapper.findComponent({ name: 'SwSidebarFilterPanel' }).props('filters') as Array<{
                property: string;
                options?: Array<{ id: string; name: string }>;
            }>
        ).find((filter) => filter.property === 'stateId');

        expect(stateFilter?.options).toEqual([
            { id: 'state-open', name: '待支付' },
            { id: 'state-paid', name: '已支付' },
        ]);
    });

    /**
     * An empty grid has two different things to say: nothing was ever booked, or the filters hid
     * everything.
     */
    it('should tell an empty shop apart from an empty result', async () => {
        const { wrapper } = await createWrapper([]);

        const emptyState = wrapper.findComponent({ name: 'MtEmptyState' });

        expect(emptyState.exists()).toBe(true);
        expect(emptyState.props('headline')).toBe('sw-payment.order.list.messageEmpty');

        await wrapper.find('.apply-filters').trigger('click');
        await flushPromises();

        expect(wrapper.findComponent({ name: 'MtEmptyState' }).props('headline')).toBe(
            'sw-empty-state.messageNoResultTitle',
        );
    });
});
