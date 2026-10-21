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

const transactions = [
    {
        id: 'transaction-1',
        customerWalletId: 'wallet-1',
        type: 'top_up',
        amount: 100.5,
        balanceAfter: 100.5,
        externalReference: 'topup-1',
        createdAt: '2026-01-02T03:04:05.000Z',
        wallet: {
            id: 'wallet-1',
            customerId: 'customer-1',
            customer: { id: 'customer-1', firstName: 'Zhang', lastName: 'San', customerNumber: '10001' },
        },
        currency: { id: 'currency-1', isoCode: 'EUR' },
        order: null,
    },
    {
        id: 'transaction-2',
        customerWalletId: 'wallet-2',
        type: 'consume',
        amount: -12,
        balanceAfter: 88.5,
        externalReference: null,
        createdAt: '2026-01-03T03:04:05.000Z',
        wallet: {
            id: 'wallet-2',
            customerId: 'customer-2',
            customer: null,
        },
        currency: { id: 'currency-1', isoCode: 'CNY' },
        order: { id: 'order-1', orderNumber: '10042' },
    },
];

type WalletTransactionsListVm = {
    defaultFilters: string[];
    listFilterOptions: Record<string, unknown>;
    transactionCriteria: {
        associations: Array<{ association: string }>;
        sortings: Array<{ field: string; order: string }>;
        filters: Array<Record<string, unknown>>;
        term: string | null;
    };
    columns: Array<{ property: string }>;
    typeFilterOptions: Array<{ id: string; name: string }>;
    hasActiveSearchOrFilter: boolean;
    activeFilterNumber: number;
    total: number;
    transactions: unknown[] | null;
    isLoading: boolean;
    term: string;
    updateCriteria: (filters: unknown[]) => void;
    typeLabel: (type: string | null) => string;
    typeVariant: (type: string | null) => string;
    formatAmount: (transaction: unknown) => string;
    formatBalanceAfter: (transaction: unknown) => string;
    orderNumber: (transaction: unknown) => string;
    customerLabel: (transaction: unknown) => string;
    formatDateOrDash: (value: string | null | undefined) => string;
};

type TypedWrapper = { vm: WalletTransactionsListVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(): Promise<{ wrapper: TypedWrapper; search: jest.Mock }> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'customer_wallet_transaction'>>(transactions)));

    const wrapper = mount(await wrapTestComponent('sw-finance-wallet-transactions-list', { sync: true }), {
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
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) => criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.finance.walletTransactions',
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

describe('module/sw-finance/page/sw-finance-wallet-transactions-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load the transactions with the wallet, the customer and the newest first', async () => {
        const { wrapper, search } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.isLoading).toBe(false);

        const criteria = wrapper.vm.transactionCriteria;

        // `addAssociation` splits a dotted path into nested associations: the top level only knows
        // `wallet`, the customer hangs in the criteria of that entry one level below
        const associations = criteria.associations.map((association) => association.association);

        expect(associations).toContain('wallet');
        expect(associations).toContain('currency');
        expect(associations).toContain('order');

        const walletEntry = criteria.associations.find((association) => association.association === 'wallet') as unknown as {
            criteria: { associations: Array<{ association: string }> };
        };

        expect(walletEntry.criteria.associations.map((association) => association.association)).toContain('customer');

        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
    });

    /**
     * A ledger entry carries no searchable text of its own: what an operator knows is the name or the
     * number of the customer, or the reference the entry was booked with. A plain `term` would not find
     * any of them.
     */
    it('should search the customer name, the customer number and the external reference', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.term = '10001';

        const criteria = wrapper.vm.transactionCriteria;
        const searchFilter = criteria.filters[0] as {
            type: string;
            operator: string;
            queries: Array<{ field: string; type: string; value: string }>;
        };

        expect(searchFilter.type).toBe('multi');
        expect(searchFilter.operator).toBe('OR');
        expect(searchFilter.queries.map((query) => query.field)).toEqual([
            'wallet.customer.firstName',
            'wallet.customer.lastName',
            'wallet.customer.customerNumber',
            'externalReference',
        ]);

        searchFilter.queries.forEach((query) => {
            expect(query.type).toBe('contains');
            expect(query.value).toBe('10001');
        });
    });

    it('should expose the columns of the ledger in its order', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.columns.map((column) => column.property)).toEqual([
            'customer',
            'type',
            'amount',
            'balanceAfter',
            'order',
            'createdAt',
        ]);
    });

    it('should have a default filter for every filter option', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.defaultFilters).toEqual(['type-filter', 'created-filter']);

        wrapper.vm.defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });

    /**
     * The badge vocabulary of the component library has five values and neither `success` nor `warning`
     * is one of them - a type mapped to a word that does not exist renders an unstyled badge.
     */
    it('should map every transaction type to a badge variant of the component library', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.typeVariant('top_up')).toBe('positive');
        expect(wrapper.vm.typeVariant('refund')).toBe('positive');
        expect(wrapper.vm.typeVariant('consume')).toBe('attention');
        expect(wrapper.vm.typeVariant('adjust')).toBe('neutral');
        expect(wrapper.vm.typeVariant('a-type-of-the-future')).toBe('neutral');
        expect(wrapper.vm.typeVariant(null)).toBe('neutral');
    });

    it('should label a type through the snippet and fall back to the technical value', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.typeLabel('top_up')).toBe('sw-finance.walletTransactions.type.top_up');
        // `$te` always answers true in the test environment, so an unknown type yields its key as
        // well instead of the technical fallback - which is what this asserts.
        expect(wrapper.vm.typeLabel('a-type-of-the-future')).toBe('sw-finance.walletTransactions.type.a-type-of-the-future');
        expect(wrapper.vm.typeLabel(null)).toBe('–');

        expect(wrapper.vm.typeFilterOptions).toEqual([
            { id: 'top_up', name: 'sw-finance.walletTransactions.type.top_up' },
            { id: 'refund', name: 'sw-finance.walletTransactions.type.refund' },
            { id: 'consume', name: 'sw-finance.walletTransactions.type.consume' },
            { id: 'adjust', name: 'sw-finance.walletTransactions.type.adjust' },
        ]);
    });

    /**
     * The amount of a transaction is already in the main unit of its currency - unlike the amount of a
     * transfer, which is in the smallest unit. Dividing it again would show 1.01 instead of 100.50. An
     * incoming entry is marked with a plus, an outgoing one keeps the minus of the currency filter.
     */
    it('should format the amount with its direction and never divide it', async () => {
        const { wrapper } = await createWrapper();
        const currencyFilter = Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;

        expect(wrapper.vm.formatAmount(transactions[0])).toBe(`+${currencyFilter(100.5, 'EUR')}`);
        expect(wrapper.vm.formatAmount(transactions[1])).toBe(currencyFilter(-12, 'CNY'));
        expect(wrapper.vm.formatAmount({ amount: null, currency: { isoCode: 'CNY' } })).toBe('–');
    });

    it('should format the balance after the entry with the currency of the entry', async () => {
        const { wrapper } = await createWrapper();
        const currencyFilter = Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;

        expect(wrapper.vm.formatBalanceAfter(transactions[0])).toBe(currencyFilter(100.5, 'EUR'));
        expect(wrapper.vm.formatBalanceAfter(transactions[1])).toBe(currencyFilter(88.5, 'CNY'));
        expect(wrapper.vm.formatBalanceAfter({ balanceAfter: null, currency: null })).toBe('–');
    });

    it('should show the order number and fall back to a dash', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.orderNumber(transactions[0])).toBe('–');
        expect(wrapper.vm.orderNumber(transactions[1])).toBe('10042');
    });

    it('should show the customer with the customer number and fall back to the wallet', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.customerLabel(transactions[0])).toBe('Zhang San (10001)');
        expect(wrapper.vm.customerLabel(transactions[1])).toBe('customer-2');
        expect(wrapper.vm.customerLabel({ wallet: null })).toBe('–');
    });

    it('should render a dash for every missing timestamp', async () => {
        const { wrapper } = await createWrapper();
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;

        expect(wrapper.vm.formatDateOrDash(null)).toBe('–');
        expect(wrapper.vm.formatDateOrDash(undefined)).toBe('–');
        expect(wrapper.vm.formatDateOrDash('2026-01-02T03:04:05.000Z')).toBe(dateFilter('2026-01-02T03:04:05.000Z'));
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

        const filters = [Criteria.equalsAny('type', ['top_up'])];
        wrapper.vm.updateCriteria(filters);
        await flushPromises();

        expect(wrapper.vm.transactionCriteria.filters).toEqual(filters);
        expect(search).toHaveBeenCalled();
    });
});
