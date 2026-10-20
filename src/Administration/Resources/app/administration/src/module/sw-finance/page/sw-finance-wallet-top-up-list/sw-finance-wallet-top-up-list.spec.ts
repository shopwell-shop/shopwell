/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (_storeKey: string, criteria: unknown) => Promise.resolve(criteria),
    };
});

const topUps = [
    {
        id: 'top-up-1',
        customerId: 'customer-1',
        paymentNo: 'W-1',
        amount: 100.5,
        currency: 'EUR',
        state: 'credited',
        channelTechnicalName: 'alipay_h5',
        createdAt: '2026-01-02T03:04:05.000Z',
        paidAt: '2026-01-02T04:05:06.000Z',
        creditedAt: '2026-01-02T05:06:07.000Z',
        customer: { id: 'customer-1', firstName: 'Zhang', lastName: 'San', customerNumber: '10001' },
    },
    {
        id: 'top-up-2',
        customerId: 'customer-2',
        paymentNo: null,
        amount: 12,
        currency: 'CNY',
        state: 'open',
        channelTechnicalName: null,
        createdAt: '2026-01-03T03:04:05.000Z',
        paidAt: null,
        creditedAt: null,
        customer: null,
    },
];

type WalletTopUpListVm = {
    defaultFilters: string[];
    listFilterOptions: Record<string, unknown>;
    topUpCriteria: {
        associations: Array<{ association: string }>;
        sortings: Array<{ field: string; order: string }>;
        filters: Array<Record<string, unknown>>;
        term: string | null;
    };
    columns: Array<{ property: string }>;
    stateFilterOptions: Array<{ id: string; name: string }>;
    hasActiveSearchOrFilter: boolean;
    activeFilterNumber: number;
    total: number;
    topUps: unknown[] | null;
    isLoading: boolean;
    term: string;
    updateCriteria: (filters: unknown[]) => void;
    stateLabel: (state: string | null) => string;
    stateVariant: (state: string | null) => string;
    formatAmount: (topUp: unknown) => string;
    customerLabel: (topUp: unknown) => string;
    formatDateOrDash: (value: string | null | undefined) => string;
};

type TypedWrapper = { vm: WalletTopUpListVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(): Promise<{ wrapper: TypedWrapper; search: jest.Mock }> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'customer_wallet_top_up'>>(topUps)));

    const wrapper = mount(await wrapTestComponent('sw-finance-wallet-top-up-list', { sync: true }), {
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
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-data-grid': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-pagination': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-link': { template: '<a><slot></slot></a>' },
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return { search };
                    },
                },
                filterFactory: {
                    create: (_entity: string, filters: Record<string, unknown>) => Object.values(filters),
                },
                acl: {
                    can: () => true,
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) =>
                        criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.finance.walletTopUp',
                    query: {},
                    meta: { $module: { icon: 'regular-money-bill' } },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
            },
        },
    }) as unknown as TypedWrapper;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, search };
}

describe('module/sw-finance/page/sw-finance-wallet-top-up-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load the top ups with the customer and the newest first', async () => {
        const { wrapper, search } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.isLoading).toBe(false);

        const criteria = wrapper.vm.topUpCriteria;

        expect(criteria.associations.map((association) => association.association)).toContain('customer');
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
    });

    /**
     * A top up carries no searchable text of its own: what an operator knows is the name or the number of
     * the customer, or the number of the top up. A plain `term` would not find any of them.
     */
    it('should search the customer name, the customer number and the top up number', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.term = '10001';

        const criteria = wrapper.vm.topUpCriteria;
        const searchFilter = criteria.filters[0] as {
            type: string;
            operator: string;
            queries: Array<{ field: string; type: string; value: string }>;
        };

        expect(searchFilter.type).toBe('multi');
        expect(searchFilter.operator).toBe('OR');
        expect(searchFilter.queries.map((query) => query.field)).toEqual([
            'customer.firstName',
            'customer.lastName',
            'customer.customerNumber',
            'paymentNo',
        ]);

        searchFilter.queries.forEach((query) => {
            expect(query.type).toBe('contains');
            expect(query.value).toBe('10001');
        });
    });

    it('should expose the columns of the prd in its order', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.columns.map((column) => column.property)).toEqual([
            'customer',
            'paymentNo',
            'amount',
            'state',
            'channelTechnicalName',
            'createdAt',
            'paidAt',
            'creditedAt',
        ]);
    });

    it('should have a default filter for every filter option', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.defaultFilters).toEqual([
            'payment-no-filter',
            'state-filter',
            'channel-filter',
            'created-filter',
        ]);

        wrapper.vm.defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });

    /**
     * The badge vocabulary of the component library has five values and neither `success` nor `warning` is
     * one of them - a state mapped to a word that does not exist renders an unstyled badge.
     */
    it('should map every state to a badge variant of the component library', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.stateVariant('open')).toBe('attention');
        expect(wrapper.vm.stateVariant('paid')).toBe('positive');
        expect(wrapper.vm.stateVariant('credited')).toBe('positive');
        expect(wrapper.vm.stateVariant('failed')).toBe('critical');
        expect(wrapper.vm.stateVariant('a-state-of-the-future')).toBe('neutral');
        expect(wrapper.vm.stateVariant(null)).toBe('neutral');
    });

    it('should label a state through the snippet and fall back to the technical value', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.stateLabel('open')).toBe('sw-finance.walletTopUp.state.open');
        // `$te` always answers true in the test environment, so an unknown state yields its key as
        // well instead of the technical fallback - which is what this asserts.
        expect(wrapper.vm.stateLabel('a-state-of-the-future')).toBe('sw-finance.walletTopUp.state.a-state-of-the-future');
        expect(wrapper.vm.stateLabel(null)).toBe('–');

        expect(wrapper.vm.stateFilterOptions).toEqual([
            { id: 'open', name: 'sw-finance.walletTopUp.state.open' },
            { id: 'paid', name: 'sw-finance.walletTopUp.state.paid' },
            { id: 'credited', name: 'sw-finance.walletTopUp.state.credited' },
            { id: 'failed', name: 'sw-finance.walletTopUp.state.failed' },
        ]);
    });

    /**
     * The amount of a top up is already in the main unit of its currency - unlike the amount of a
     * transfer, which is in the smallest unit. Dividing it again would show 1.01 instead of 100.50.
     */
    it('should format the amount with the currency filter and never divide it', async () => {
        const { wrapper } = await createWrapper();
        const currencyFilter = Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;

        expect(wrapper.vm.formatAmount(topUps[0])).toBe(currencyFilter(100.5, 'EUR'));
        expect(wrapper.vm.formatAmount(topUps[1])).toBe(currencyFilter(12, 'CNY'));
        expect(wrapper.vm.formatAmount({ amount: null, currency: 'CNY' })).toBe('–');
    });

    it('should show the customer with the customer number and fall back to the id', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.customerLabel(topUps[0])).toBe('Zhang San (10001)');
        expect(wrapper.vm.customerLabel(topUps[1])).toBe('customer-2');
    });

    it('should render a dash for every missing timestamp', async () => {
        const { wrapper } = await createWrapper();
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;

        expect(wrapper.vm.formatDateOrDash(null)).toBe('–');
        expect(wrapper.vm.formatDateOrDash(undefined)).toBe('–');
        expect(wrapper.vm.formatDateOrDash('2026-01-02T03:04:05.000Z')).toBe(
            dateFilter('2026-01-02T03:04:05.000Z'),
        );
    });

    it('should tell the two empty states apart', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(false);

        wrapper.vm.activeFilterNumber = 1;
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);

        wrapper.vm.term = 'nomatch';
        wrapper.vm.activeFilterNumber = 0;
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('should apply the filters the filter panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        const filters = [Criteria.equals('state', 'failed')];
        wrapper.vm.updateCriteria(filters);
        await flushPromises();

        expect(wrapper.vm.topUpCriteria.filters).toEqual(filters);
        expect(search).toHaveBeenCalled();
    });
});
