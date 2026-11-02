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
    { id: 'state-open', name: 'open', technicalName: 'open', translated: { name: '退款中' } },
    { id: 'state-finished', name: 'finished', technicalName: 'finished', translated: { name: '已退款' } },
];

/**
 * The filter set the panel hands over when the merchant applies one.
 */
const APPLIED_FILTERS = [{ type: 'equalsAny', field: 'paymentOrder.channelTechnicalName', value: ['wechat_h5'] }];

function buildRefunds(): Array<Record<string, unknown>> {
    return [
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
}

/**
 * A search result as the repository delivers it: the entities and the total the grid reads.
 */
function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

async function createWrapper(rows: Array<Record<string, unknown>> = buildRefunds()): Promise<{
    wrapper: VueWrapper;
    search: jest.Mock;
    stateSearch: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'payment_refund'>>(rows)));

    const stateSearch = jest.fn(() => Promise.resolve(collection<EntityCollection<'state_machine_state'>>(STATES)));

    const wrapper = mount(await wrapTestComponent('sw-payment-refund-list', { sync: true }), {
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
                'sw-search-bar': { template: '<div class="search-bar"></div>' },
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
                            <span class="cell-order"><slot name="column-paymentOrderId" :item="item"></slot></span>
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

describe('module/sw-payment/page/sw-payment-refund-list', () => {
    beforeEach(() => {
        // the acl service reads the roles of the test account from the global scope
        (globalThis as { activeAclRoles?: string[] }).activeAclRoles = ['payment_refund.viewer'];
    });

    it('should hand the panel one filter per filterable column', async () => {
        const { wrapper } = await createWrapper();

        const panel = wrapper.findComponent({ name: 'SwSidebarFilterPanel' });

        expect(panel.props('defaults')).toEqual([
            'refund-no-filter',
            'out-refund-no-filter',
            'channel-filter',
            'state-filter',
        ]);
        expect(panel.props('filters')).toHaveLength(4);
    });

    it('should load the refunds and the states of the machine once', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(stateSearch).toHaveBeenCalledTimes(1);
        expect(wrapper.findComponent({ name: 'SwEntityListing' }).props('isLoading')).toBe(false);
        expect(wrapper.findComponent({ name: 'SwEntityListing' }).props('dataSource')).toHaveLength(1);
    });

    it('should sort by the newest refund and carry the payment of every row', async () => {
        const { wrapper, search } = await createWrapper();

        const criteria = lastCriteria(search);

        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
        expect(criteria.associations.map((association) => association.association)).toContain('paymentOrder');
        expect(wrapper.findComponent({ name: 'SwEntityListing' }).props('sortBy')).toBe('createdAt');
    });

    it('should apply the filters the panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        await wrapper.find('.apply-filters').trigger('click');
        await flushPromises();

        expect(search).toHaveBeenCalled();
        expect(lastCriteria(search).filters).toEqual(APPLIED_FILTERS);
    });

    it('should render the state of a refund, its amount and the payment it belongs to', async () => {
        const { wrapper } = await createWrapper();

        const badge = wrapper.find('.cell-state .badge');

        expect(badge.text()).toBe('已退款');
        expect(badge.attributes('data-variant')).toBe('positive');
        expect(cells(wrapper, 'cell-amount')).toEqual([Shopwell.Utils.format.currency(5, 'CNY', 2)]);
        expect(cells(wrapper, 'cell-order')).toEqual(['P-1']);
    });

    it('should fall back to the merchant number of a refund the gateway booked without a payment', async () => {
        const { wrapper } = await createWrapper([
            { id: 'refund-2', refundNo: 'R-2', amount: 100, currency: 'CNY', createdAt: '2026-01-02T03:04:05.000Z' },
        ]);

        expect(cells(wrapper, 'cell-order')).toEqual(['–']);
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
            { id: 'state-open', name: '退款中' },
            { id: 'state-finished', name: '已退款' },
        ]);
    });

    /**
     * An empty grid has two different things to say: nothing was ever refunded, or the filters hid
     * everything.
     */
    it('should tell an empty shop apart from an empty result', async () => {
        const { wrapper } = await createWrapper([]);

        expect(wrapper.findComponent({ name: 'MtEmptyState' }).props('headline')).toBe(
            'sw-payment.refund.list.messageEmpty',
        );

        await wrapper.find('.apply-filters').trigger('click');
        await flushPromises();

        expect(wrapper.findComponent({ name: 'MtEmptyState' }).props('headline')).toBe(
            'sw-empty-state.messageNoResultTitle',
        );
    });
});
