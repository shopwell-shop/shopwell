/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

type RouteQuery = Record<string, unknown>;

type RouterMock = {
    push: { mock: { calls: Array<[RouteQuery]> } };
    replace: { mock: { calls: Array<[RouteQuery]> } };
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- double of a framework service, registered once for the file
Shopwell.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (_storeKey: string, criteria: unknown) => criteria,
    };
});

type CapturedCriteria = {
    page: number;
    limit: number;
    term: string | null;
    filters: { type: string; field: string; value: unknown }[];
    sortings: { field: string; order: string }[];
    associations: { association: string }[];
};

const purchase = {
    id: 'purchase-1',
    customerId: 'customer-1',
    planName: 'Gold, one year',
    amount: 199,
    currency: 'EUR',
    state: 'activated',
    paidAt: '2026-01-02T10:00:00.000Z',
    createdAt: '2026-01-02T09:59:00.000Z',
    customer: {
        id: 'customer-1',
        name: 'Ada Lovelace',
        customerNumber: '10001',
    },
};

let capturedCriteria: CapturedCriteria | null = null;
let searchResult: unknown[] = [];

const purchaseRepository = {
    search: jest.fn((criteria: CapturedCriteria) => {
        capturedCriteria = criteria;

        return Promise.resolve(Object.assign([...searchResult], { total: searchResult.length }));
    }),
};

/**
 * The grid is stubbed so that it renders the slots of the page and nothing else - the cells are what
 * this page is responsible for, the grid around them has its own specs.
 */
const entityListingStub = {
    name: 'sw-entity-listing',
    props: [
        'dataSource',
        'columns',
        'sortBy',
        'sortDirection',
        'isLoading',
    ],
    template: `
        <div class="sw-entity-listing">
            <div
                v-for="item in dataSource"
                :key="item.id"
                class="sw-data-grid__row"
            >
                <div class="sw-data-grid__cell sw-data-grid__cell--customer">
                    <slot name="column-customer" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--plan">
                    <slot name="column-planName" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--amount">
                    <slot name="column-amount" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--state">
                    <slot name="column-state" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--paid-at">
                    <slot name="column-paidAt" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--created-at">
                    <slot name="column-createdAt" :item="item" />
                </div>
            </div>
            <button
                class="sw-entity-listing__page-change"
                @click="$emit('page-change', { page: 2, limit: 10 })"
            >page</button>
            <button
                class="sw-entity-listing__column-sort"
                @click="$emit('column-sort', { dataIndex: 'amount', naturalSorting: false })"
            >sort</button>
        </div>`,
};

const sidebarFilterPanelStub = {
    name: 'sw-sidebar-filter-panel',
    props: [
        'entity',
        'storeKey',
        'filters',
        'defaults',
        'activeFilterNumber',
    ],
    template: `
        <div class="sw-sidebar-filter-panel">
            <button
                class="sw-sidebar-filter-panel__apply"
                @click="$emit('criteria-changed', [{ type: 'equals', field: 'state', value: 'failed' }])"
            >apply</button>
        </div>`,
};

async function createWrapper(): Promise<{
    wrapper: ReturnType<typeof mount>;
    router: RouterMock;
}> {
    const router: RouterMock = { push: jest.fn(), replace: jest.fn() };

    const wrapper = mount(await wrapTestComponent('sw-membership-purchase-list', { sync: true }), {
        global: {
            mocks: {
                // the wording is not under test, only which key the page looks up
                $t: (key: string) => key,
                $route: {
                    name: 'sw.membership.purchases',
                    query: { page: 1, limit: 25 },
                    meta: { $module: { icon: 'regular-clipboard' } },
                },
                $router: router,
            },
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
                'sw-entity-listing': entityListingStub,
                'sw-sidebar-filter-panel': sidebarFilterPanelStub,
                'sw-sidebar': { template: '<div><slot></slot></div>' },
                'sw-sidebar-item': {
                    // declared, or the native click of the button would reach the page twice
                    emits: ['click'],
                    template: '<button class="sw-sidebar-item__refresh" @click="$emit(\'click\')" />',
                },
                'sw-search-bar': {
                    props: ['initialSearch'],
                    template: '<button class="sw-search-bar__search" @click="$emit(\'search\', \'Gold\')" />',
                },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="mt-badge" :data-variant="variant"><slot></slot></span>',
                },
                'mt-link': {
                    props: ['to'],
                    template: '<a class="mt-link" :data-target="JSON.stringify(to)"><slot></slot></a>',
                },
                'mt-empty-state': { props: ['headline'], template: '<div class="mt-empty-state">{{ headline }}</div>' },
                'sw-time-ago': { props: ['date'], template: '<span class="sw-time-ago">{{ date }}</span>' },
                'mt-icon': true,
            },
            provide: {
                repositoryFactory: {
                    create: (name: string) => {
                        if (name === 'membership_purchase') {
                            return purchaseRepository;
                        }

                        throw new Error(`No repository for ${name} configured`);
                    },
                },
                filterFactory: {
                    create: (entityName: string, filters: Record<string, unknown>) =>
                        Object.entries(filters).map(([name, filter]) => ({ name, ...(filter as object) })),
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) => criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, router };
}

describe('src/module/sw-membership/page/sw-membership-purchase-list', () => {
    beforeEach(() => {
        capturedCriteria = null;
        searchResult = [purchase];
        purchaseRepository.search.mockClear();
    });

    it('asks for the purchases of the shop when the page opens', async () => {
        await createWrapper();

        expect(purchaseRepository.search).toHaveBeenCalledTimes(1);
    });

    it('loads the customer of a purchase and shows the newest first', async () => {
        await createWrapper();

        const associations = (capturedCriteria?.associations ?? []).map((association) => association.association);

        expect(associations).toContain('customer');
        expect(capturedCriteria?.sortings[0]).toMatchObject({ field: 'createdAt', order: 'DESC' });
    });

    it('renders the customer of a purchase as a link to the customer detail page', async () => {
        const { wrapper } = await createWrapper();

        const link = wrapper.find('.sw-data-grid__cell--customer .mt-link');

        expect(link.text()).toBe('Ada Lovelace (10001)');
        expect(link.attributes('data-target')).toContain('sw.customer.detail');
        expect(link.attributes('data-target')).toContain('customer-1');
    });

    it('falls back to the customer id when the customer was not loaded', async () => {
        searchResult = [{ ...purchase, customer: null }];

        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-data-grid__cell--customer').text()).toBe('customer-1');
    });

    it('renders the plan, the amount and the state of a purchase', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-data-grid__cell--plan').text()).toBe('Gold, one year');
        expect(wrapper.find('.sw-data-grid__cell--amount').text()).toContain('199');
        expect(wrapper.find('.sw-data-grid__cell--state').text()).toBe('sw-membership.purchase.state.activated');
    });

    it('marks a paid membership as positive and a failed one as critical', async () => {
        const { wrapper: activated } = await createWrapper();
        expect(activated.find('.mt-badge').attributes('data-variant')).toBe('positive');

        searchResult = [{ ...purchase, state: 'failed' }];
        const { wrapper: failed } = await createWrapper();
        expect(failed.find('.mt-badge').attributes('data-variant')).toBe('critical');

        searchResult = [{ ...purchase, state: 'open' }];
        const { wrapper: open } = await createWrapper();
        expect(open.find('.mt-badge').attributes('data-variant')).toBe('info');

        searchResult = [{ ...purchase, state: 'something-new' }];
        const { wrapper: unknown } = await createWrapper();
        expect(unknown.find('.mt-badge').attributes('data-variant')).toBe('neutral');
    });

    it('renders a dash instead of an empty paid date', async () => {
        searchResult = [{ ...purchase, paidAt: null }];

        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-data-grid__cell--paid-at').text()).toBe('-');
    });

    it('searches by the term the operator typed and jumps back to the first page', async () => {
        const { wrapper, router } = await createWrapper();

        await wrapper.find('.sw-search-bar__search').trigger('click');
        await flushPromises();

        // the listing mixin writes the term into the route, the route watcher reloads the queue
        const query = router.push.mock.calls[router.push.mock.calls.length - 1][0].query;

        expect(query).toMatchObject({ term: 'Gold', page: 1 });
    });

    it('passes the page the operator picked into the route', async () => {
        const { wrapper, router } = await createWrapper();

        await wrapper.find('.sw-entity-listing__page-change').trigger('click');
        await flushPromises();

        const query = router.push.mock.calls[router.push.mock.calls.length - 1][0].query;

        expect(query).toMatchObject({ page: 2 });
    });

    it('sorts by the column the operator picked', async () => {
        const { wrapper, router } = await createWrapper();

        await wrapper.find('.sw-entity-listing__column-sort').trigger('click');
        await flushPromises();

        const query = router.push.mock.calls[router.push.mock.calls.length - 1][0].query;

        expect(query).toMatchObject({ sortBy: 'amount', sortDirection: 'ASC' });
    });

    it('filters by the state the operator picked and jumps back to the first page', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.sw-sidebar-filter-panel__apply').trigger('click');
        await flushPromises();

        const stateFilter = capturedCriteria?.filters.find((filter) => filter.field === 'state');

        expect(stateFilter?.value).toBe('failed');
        expect(capturedCriteria?.page).toBe(1);
    });

    it('offers one filter per column the operator can narrow the queue with', async () => {
        const { wrapper } = await createWrapper();

        const panel = wrapper.findComponent({ name: 'sw-sidebar-filter-panel' });

        expect(panel.props('storeKey')).toBe('grid.filter.membership_purchase');
        expect(panel.props('entity')).toBe('membership_purchase');
        expect(JSON.stringify(panel.props('filters'))).toContain('plan-filter');
        expect(JSON.stringify(panel.props('filters'))).toContain('state-filter');
    });

    it('reloads the list when the refresh button was clicked', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.sw-sidebar-item__refresh').trigger('click');
        await flushPromises();

        expect(purchaseRepository.search).toHaveBeenCalledTimes(2);
    });

    it('shows an empty state instead of an empty grid when nothing was bought yet', async () => {
        searchResult = [];

        const { wrapper } = await createWrapper();

        expect(wrapper.find('.mt-empty-state').text()).toBe('sw-membership.purchase.list.emptyStateHeadline');
        expect(wrapper.find('.sw-data-grid__row').exists()).toBe(false);
    });
});
